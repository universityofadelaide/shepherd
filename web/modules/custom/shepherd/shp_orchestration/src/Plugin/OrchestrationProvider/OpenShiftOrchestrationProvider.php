<?php

namespace Drupal\shp_orchestration\Plugin\OrchestrationProvider;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\shp_custom\Service\StringGenerator;
use Drupal\shp_orchestration\OrchestrationProviderBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UniversityOfAdelaide\OpenShift\Client as OpenShiftClient;
use UniversityOfAdelaide\OpenShift\ClientException;

/**
 * OpenShiftOrchestrationProvider.
 *
 * @OrchestrationProvider(
 *   id = "openshift_orchestration_provider",
 *   name = "OpenShift",
 *   description = @Translation("OpenShift provider to perform orchestration tasks"),
 *   schema = "openshift.orchestration_provider",
 *   config_entity_id = "openshift"
 * )
 */
class OpenShiftOrchestrationProvider extends OrchestrationProviderBase {

  /**
   * OpenShift client.
   *
   * @var \UniversityOfAdelaide\OpenShift\Client
   *   PHP OpenShift client.
   */
  protected $client;

  /**
   * Sepherd custom string generator.
   *
   * @var \Drupal\shp_custom\Service\StringGenerator
   *   String generator.
   */
  protected $stringGenerator;

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);
    $this->client = new OpenShiftClient(
      $this->configEntity->endpoint,
      $this->configEntity->token,
      $this->configEntity->namespace,
      $this->configEntity->verify_tls
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition
    );
    $instance->injectServices(
      $container->get('shp_custom.string_generator'),
      $container->get('messenger')
    );
    return $instance;
  }

  /**
   * Inject services to this plugin without changing base constructor.
   *
   * @param \Drupal\shp_custom\Service\StringGenerator $string_generator
   *   Shepherd custom string generator.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   *
   * @todo: This really is just a stop-gap until we properly refactor.
   */
  public function injectServices(StringGenerator $string_generator, MessengerInterface $messenger) {
    $this->stringGenerator = $string_generator;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public function createdProject(string $name, string $builder_image, string $source_repo, string $source_ref = 'master', string $source_secret = NULL, array $environment_variables = []) {
    $sanitised_project_name = self::sanitise($name);
    $sanitised_source_ref = self::sanitise($source_ref);
    $image_stream_tag = $sanitised_project_name . ':' . $sanitised_source_ref;
    $build_config_name = $sanitised_project_name . '-' . $sanitised_source_ref;

    $formatted_env_vars = $this->formatEnvVars($environment_variables);

    try {
      $image_stream = $this->client->generateImageStreamConfig($sanitised_project_name);
      $this->client->createImageStream($image_stream);
      $this->createBuildConfig($build_config_name, $source_ref, $source_repo, $builder_image, $source_secret, $image_stream_tag, $formatted_env_vars);
    }
    catch (ClientException $e) {
      $this->handleClientException($e);
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function updatedProject(string $name, string $builder_image, string $source_repo, string $source_ref = 'master', string $source_secret = NULL, array $environment_variables = []) {
    $sanitised_name = self::sanitise($name);

    // Package config for the client.
    $build_data = [
      'git' => [
        'ref' => $source_ref,
        'uri' => $source_repo,
      ],
      'source' => [
        'type' => 'DockerImage',
        'name' => $builder_image,
      ],
    ];

    try {
      $this->client->updateBuildConfig(
        $sanitised_name . '-' . $source_ref,
        $source_secret,
        $sanitised_name . ':' . $source_ref,
        $build_data
      );
    }
    catch (ClientException $e) {
      $this->handleClientException($e);
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function deletedProject($name) {
    // @todo Implement deletedProject() method.
  }

  /**
   * Create a build config in OpenShift.
   *
   * @param $build_config_name
   * @param $source_ref
   * @param $source_repo
   * @param $builder_image
   * @param $formatted_env_vars
   * @param $source_secret
   * @param $image_stream_tag
   *
   * @return bool
   *   Created or already exists = TRUE. Fail = FALSE.
   */
  protected function createBuildConfig(string $build_config_name, string $source_ref, string $source_repo, string $builder_image, string $source_secret, string $image_stream_tag, array $formatted_env_vars) {
    // Create build config if it doesn't exist.
    if (!$this->client->getBuildConfig($build_config_name)) {
      $build_data = $this->formatBuildData($source_ref, $source_repo, $builder_image, $formatted_env_vars);

      $build_config = $this->client->generateBuildConfig(
        $build_config_name,
        $source_secret,
        $image_stream_tag,
        $build_data
      );

      try {
        $this->client->createBuildConfig($build_config);
      }
      catch (ClientException $e) {
        $this->handleClientException($e);
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function createdEnvironment(
    string $project_name,
    string $short_name,
    string $site_id,
    string $environment_id,
    string $environment_url,
    string $builder_image,
    string $domain,
    string $path,
    string $source_repo,
    string $source_ref = 'master',
    string $source_secret = NULL,
    string $storage_class = '',
    bool $update_on_image_change = FALSE,
    bool $cron_suspended = FALSE,
    array $environment_variables = [],
    array $secrets = [],
    array $probes = [],
    array $cron_jobs = [],
    array $annotations = []
  ) {
    // @todo Refactor this. _The complexity is too damn high!_

    $sanitised_project_name = self::sanitise($project_name);
    $sanitised_source_ref = self::sanitise($source_ref);
    $deployment_name = self::generateDeploymentName($environment_id);
    $image_stream_tag = $sanitised_project_name . ':' . $sanitised_source_ref;
    $build_config_name = $sanitised_project_name . '-' . $sanitised_source_ref;
    $formatted_env_vars = $this->formatEnvVars($environment_variables, $deployment_name);

    // Tell, don't ask (to create a build config).
    $this->createBuildConfig($build_config_name, $source_ref, $source_repo, $builder_image, $source_secret, $image_stream_tag, $formatted_env_vars);

    if (!$volumes = $this->setupVolumes($project_name, $deployment_name, $storage_class, $secrets)) {
      return FALSE;
    }

    $deploy_data = $this->formatDeployData(
      $deployment_name,
      $formatted_env_vars,
      $environment_url,
      $site_id,
      $environment_id
    );

    $deployment_config = $this->client->generateDeploymentConfig(
      $deployment_name,
      $image_stream_tag,
      $sanitised_project_name,
      $update_on_image_change,
      $volumes,
      $deploy_data,
      $probes
    );

    try {
      $this->client->createDeploymentConfig($deployment_config);
    }
    catch (ClientException $e) {
      $this->handleClientException($e);
      return FALSE;
    }

    $image_stream = $this->client->getImageStream($sanitised_project_name);
    $this->createCronJobs(
      $deployment_name,
      $sanitised_source_ref,
      $cron_suspended,
      $cron_jobs,
      $image_stream,
      $volumes,
      $deploy_data
    );

    if (!$update_on_image_change) {
      // We need to check if the image is already 'built', or we get an error.
      $build_status = $this->client->getBuilds('', 'buildconfig=' . $build_config_name);
      if (count($build_status['items']) && $build_status['items'][0]['status']['phase'] === 'Complete') {
        $this->client->instantiateDeploymentConfig($deployment_name);
      }
      else {
        $this->messenger->addStatus(t('Build not yet complete, manual triggering of deployment will be required.'));
      }
    }

    // @todo - make port a var and great .. so great .. yuge!
    $port = 8080;
    try {
      $this->client->createService($deployment_name, $deployment_name, $port, $port, $deployment_name);
      $this->client->createRoute($deployment_name, $deployment_name, $domain, $path, $annotations);
    }
    catch (ClientException $e) {
      $this->handleClientException($e);
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function updatedEnvironment(
    string $project_name,
    string $short_name,
    string $site_id,
    string $environment_id,
    string $environment_url,
    string $builder_image,
    string $domain,
    string $path,
    string $source_repo,
    string $source_ref = 'master',
    string $source_secret = NULL,
    string $storage_class = '',
    bool $update_on_image_change = FALSE,
    bool $cron_suspended = FALSE,
    array $environment_variables = [],
    array $secrets = [],
    array $probes = [],
    array $cron_jobs = [],
    array $annotations = []
  ) {
    // @todo Refactor this too. Not DRY enough.

    $sanitised_project_name = self::sanitise($project_name);
    $deployment_name = self::generateDeploymentName($environment_id);
    $formatted_env_vars = $this->formatEnvVars($environment_variables, $deployment_name);

    if (!$volumes = $this->setupVolumes($project_name, $deployment_name, $storage_class, $secrets)) {
      return FALSE;
    }

    $deploy_data = $this->formatDeployData(
      $deployment_name,
      $formatted_env_vars,
      $environment_url,
      $site_id,
      $environment_id
    );

    // Remove all the existing cron jobs.
    $this->client->deleteCronJob('', 'app=' . $deployment_name);

    // Re-create all the cron jobs.
    $image_stream = $this->client->getImageStream($sanitised_project_name);
    $this->createCronJobs(
      $deployment_name,
      $source_ref,
      $cron_suspended,
      $cron_jobs,
      $image_stream,
      $volumes,
      $deploy_data
    );

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function deletedEnvironment(
    string $project_name,
    string $short_name,
    string $environment_id
  ) {
    $deployment_name = self::generateDeploymentName($environment_id);

    try {
      // Scale the pods to zero, then delete the pod creators.
      // @todo - placing the logic here .. as its not clear what level of logic we should place in client.
      $deploymentConfigs = $this->client->getDeploymentConfigs('app=' . $deployment_name);
      foreach ($deploymentConfigs['items'] as $deploymentConfig) {
        $this->client->updateDeploymentConfig($deploymentConfig['metadata']['name'], $deploymentConfig, [
          'apiVersion' => 'v1',
          'kind' => 'DeploymentConfig',
          'spec' => [
            'replicas' => 0,
          ],
        ]);
      }
      $this->client->deleteCronJob('', 'app=' . $deployment_name);
      $this->client->deleteJob('', 'app=' . $deployment_name);
      $this->client->deleteRoute($deployment_name);
      $this->client->deleteService($deployment_name);
      $this->client->deleteDeploymentConfig($deployment_name);
      $this->client->deleteReplicationControllers('', 'app=' . $deployment_name);

      // Now the things not in the typically visible ui.
      $this->client->deletePersistentVolumeClaim($deployment_name . '-shared');
      $this->client->deleteSecret($deployment_name);
    }
    catch (ClientException $e) {
      $this->handleClientException($e);
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function archivedEnvironment(
    int $environment_id
  ) {
    $site = Node::load($environment_id->field_shp_site->target_id);
    $project = Node::load($site->field_shp_project->target_id);

    $this->deletedEnvironment(
      $project->title->value,
      $site->field_shp_short_name->value,
      $environment_id
    );
  }

  /**
   * {@inheritdoc}
   */
  public function promotedEnvironment(
    string $project_name,
    string $short_name,
    int $site_id,
    int $environment_id,
    string $domain,
    string $path,
    array $annotations,
    string $source_ref = 'master',
    bool $clear_cache = TRUE
  ) {
    $site_deployment_name = self::generateDeploymentName($site_id);

    $environment_deployment_name = self::generateDeploymentName($environment_id);

    // @todo - remove the hardcoded ports.
    $port = 8080;

    if (!$this->client->getService($site_deployment_name)) {
      $this->client->createService($site_deployment_name, $site_deployment_name, $port, $port, $site_deployment_name);
    }

    if (!$this->client->getRoute($site_deployment_name)) {
      $this->client->createRoute($site_deployment_name, $site_deployment_name, $domain, $path, $annotations);
    }

    $result = $this->client->updateService($site_deployment_name, $environment_deployment_name);
    if ($result && $clear_cache) {
      // @todo - Remove drush call, it relates to a project type rather than all projects.
      $this->executeJob(
        $project_name,
        $short_name,
        $environment_id,
        $source_ref,
        "drush -r /code/web cr"
      );
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function createdSite(
    string $project_name,
    string $short_name,
    int $site_id,
    string $domain,
    string $path
  ) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function updatedSite() {
    // TODO: Implement updateSite() method.
  }

  /**
   * {@inheritdoc}
   */
  public function deletedSite(
    string $project_name,
    string $short_name,
    int $site_id
  ) {
    $deployment_name = self::generateDeploymentName($site_id);

    $this->client->deleteService($deployment_name);
    $this->client->deleteRoute($deployment_name);
  }

  /**
   * {@inheritdoc}
   */
  public function backupEnvironment(
    string $project_name,
    string $short_name,
    string $environment_id,
    string $source_ref = 'master',
    string $commands = ''
  ) {
    return $this->executeJob(
      $project_name,
      $short_name,
      $environment_id,
      $source_ref,
      $commands
    );

  }

  /**
   * {@inheritdoc}
   */
  public function restoreEnvironment(
    string $project_name,
    string $short_name,
    string $environment_id,
    string $source_ref = 'master',
    string $commands = ''
  ) {
    return $this->executeJob(
      $project_name,
      $short_name,
      $environment_id,
      $source_ref,
      $commands
    );

  }

  /**
   * {@inheritdoc}
   *
   * @todo - can this and cron job creation be combined?
   */
  public function executeJob(
    string $project_name,
    string $short_name,
    string $environment_id,
    string $source_ref = 'master',
    string $commands = ''
  ) {
    $sanitised_project_name = self::sanitise($project_name);
    $sanitised_source_ref = self::sanitise($source_ref);
    $deployment_name = self::generateDeploymentName($environment_id);

    // Retrieve existing deployment details to use where possible.
    $deployment_config = $this->client->getDeploymentConfig($deployment_name);

    $image_stream = $this->client->getImageStream($sanitised_project_name);
    $volumes = $this->generateVolumeData($project_name, $deployment_name, TRUE);
    $deploy_data = $this->formatDeployData(
      $deployment_name,
      $deployment_config['spec']['template']['spec']['containers'][0]['env'],
      $deployment_config['metadata']['annotations']['shepherdUrl'],
      $deployment_config['metadata']['labels']['site_id'],
      $environment_id
    );

    $args_array = [
      '/bin/sh',
      '-c',
      $commands,
    ];
    try {
      $response_body = $this->client->createJob(
        $deployment_name . '-' . $this->stringGenerator->generateRandomString(5),
        $image_stream['status']['dockerImageRepository'] . ':' . $sanitised_source_ref,
        $args_array,
        $volumes,
        $deploy_data
      );
    }
    catch (ClientException $e) {
      $this->handleClientException($e);
      return FALSE;
    }
    return $response_body;
  }

  /**
   * Fetch the job from the provider.
   *
   * @param string $name
   *   The job name.
   *
   * @return array|bool
   *   The job, else false.
   */
  public function getJob(string $name) {
    return $this->client->getJob($name);
  }

  /**
   * {@inheritdoc}
   */
  public function getSecret(string $name, string $key = NULL) {
    try {
      $secret = $this->client->getSecret($name);
    }
    catch (ClientException $e) {
      $this->handleClientException($e);
      return FALSE;
    }
    if (is_array($secret) && array_key_exists('data', $secret)) {
      if ($key) {
        return array_key_exists($key, $secret['data']) ? base64_decode($secret['data'][$key]) : FALSE;
      }
      return array_map('base64_decode', $secret['data']);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function createSecret(string $name, array $data) {
    try {
      return $this->client->createSecret($name, $data);
    }
    catch (ClientException $e) {
      $this->handleClientException($e);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateSecret(string $name, array $data) {
    try {
      return $this->client->updateSecret($name, $data);
    }
    catch (ClientException $e) {
      $this->handleClientException($e);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSiteEnvironmentsStatus(string $site_id) {
    try {
      $deployment_configs = $this->client->getDeploymentConfigs('site_id=' . $site_id);
    }
    catch (ClientException $e) {
      $this->handleClientException($e);
      return FALSE;
    }
    $environments_status = [];
    foreach ($deployment_configs['items'] as $deployment_config) {
      // Search through the conditions for a key of type 'available'
      // This defines if the deployment config is effectively running or not.
      $environments_status[] = $this->extractDeploymentConfigStatus($deployment_config);
    }

    return $environments_status;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironmentStatus(string $project_name, string $short_name, string $environment_id) {

    $deployment_name = self::generateDeploymentName($environment_id);

    try {
      $deployment_config = $this->client->getDeploymentConfig($deployment_name);
    }
    catch (ClientException $e) {
      $this->handleClientException($e);
      return FALSE;
    }

    return $deployment_config ? $this->extractDeploymentConfigStatus($deployment_config) : FALSE;
  }

  /**
   * Pull the status from a deployment config.
   *
   * @param array $deployment_config
   *   Deployment config.
   *
   * @return array
   *   Extracted array that contains the status, time and number of pods.
   */
  protected function extractDeploymentConfigStatus(array $deployment_config) {
    $environment_status = [];
    foreach ($deployment_config['status']['conditions'] as $condition) {
      if (strtolower($condition['type']) === 'available') {
        $environment_status = [
          'running' => $condition['status'] === 'True',
          'time' => $condition['lastUpdateTime'],
          'available_pods' => $deployment_config['status']['availableReplicas'],
        ];
        break;
      }
    }
    return $environment_status;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironmentUrl(string $project_name, string $short_name, string $environment_id) {
    $deployment_name = self::generateDeploymentName($environment_id);

    try {
      $route = $this->client->getRoute($deployment_name);
      return Url::fromUri('//' . $route['spec']['host'] . (array_key_exists('path', $route['spec']) ? $route['spec']['path'] : '/'));
    }
    catch (ClientException $e) {
      $this->handleClientException($e);
      return FALSE;
    }
    catch (\InvalidArgumentException $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTerminalUrl(string $project_name, string $short_name, string $environment_id) {
    $deployment_name = self::generateDeploymentName($environment_id);

    try {
      $pods = $this->client->getPod('', 'app=' . $deployment_name . ',environment_id=' . $environment_id);
      // If there are no running pods, return now.
      if (!count($pods['items'])) {
        return FALSE;
      }

      // Return the link to the first pod.
      $pod_name = $pods['items'][0]['metadata']['name'];
      return $this->generateOpenShiftPodUrl($pod_name, 'terminal');

    }
    catch (ClientException $e) {
      $this->handleClientException($e);
      return FALSE;
    }
    catch (\InvalidArgumentException $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLogUrl(string $project_name, string $short_name, string $environment_id) {
    $deployment_name = self::generateDeploymentName($environment_id);

    try {
      $pods = $this->client->getPod('', 'app=' . $deployment_name . ',environment_id=' . $environment_id);
      // If there are no running pods, return now.
      if (!count($pods['items'])) {
        return FALSE;
      }
      // Return the link to the first pod.
      $pod_name = $pods['items'][0]['metadata']['name'];
      return $this->generateOpenShiftPodUrl($pod_name, 'logs');

    }
    catch (ClientException $e) {
      $this->handleClientException($e);
      return FALSE;
    }
    catch (\InvalidArgumentException $e) {
      return FALSE;
    }
  }

  /**
   * Generates a url to a specific pod and view in OpenShift.
   *
   * @param string $pod_name
   *   Pod name.
   * @param string $view
   *   View/tab to display.
   *
   * @return string
   *   Url.
   */
  protected function generateOpenShiftPodUrl(string $pod_name, string $view) {
    $endpoint = $this->configEntity->endpoint;
    $namespace = $this->configEntity->namespace;

    return Url::fromUri($endpoint . '/console/project/' . $namespace . '/browse/pods/' . $pod_name, [
      'query' => [
        'tab' => $view,
      ],
      'attributes' => [
        'target' => '_blank',
      ],
    ]);
  }

  /**
   * Format an array of environment variables ready to pass to OpenShift.
   *
   * @todo - move this into the client?
   *
   * @param array $environment_variables
   *   An array of environment variables to be set for the pod.
   * @param string $deployment_name
   *   The deployment name.
   *
   * @return array
   *   The env var config array.
   */
  protected function formatEnvVars(array $environment_variables, string $deployment_name = '') {
    $formatted_env_vars = [];

    foreach ($environment_variables as $name => $value) {
      if (is_string($value)) {
        // Plain environment variable.
        $formatted_env_vars[] = [
          'name' => $name,
          'value' => $value,
        ];
      }
      elseif (is_array($value) && array_key_exists('secret', $value)) {
        // Sourced from secret.
        $formatted_env_vars[] = [
          'name' => $name,
          'valueFrom' => [
            'secretKeyRef' => [
              // If secret is '_default' use the deployment config secret.
              'name' => $value['secret'] == '_default' ? $deployment_name : $value['secret'],
              'key' => $value['secret_key'],
            ],
          ],
        ];
      }
    }

    return $formatted_env_vars;
  }

  /**
   * Format an array of deployment data ready to pass to OpenShift.
   *
   * @todo - move this into the client?
   *
   * @param string $name
   *   The name of the deployment config.
   * @param array $formatted_env_vars
   *   An array of key => value env var pairs.
   * @param string $environment_url
   *   The url of the environment being created.
   * @param int $site_id
   *   The ID of the site the environment represents.
   * @param int $environment_id
   *   The ID of the environment being created.
   *
   * @return array
   *   The deployment config array.
   */
  protected function formatDeployData(string $name, array $formatted_env_vars, string $environment_url, int $site_id, int $environment_id) {
    $deploy_data = [
      'containerPort' => 8080,
      'memory_limit' => '512Mi',
      'env_vars' => $formatted_env_vars,
      'annotations' => [
        'shepherdUrl' => $environment_url,
      ],
      'labels' => [
        'site_id' => (string) $site_id,
        'environment_id' => (string) $environment_id,
        'app' => $name,
        'deploymentconfig' => $name,
      ],
    ];

    // If set, add uid and gid from config to deploy data.
    if (strlen($this->configEntity->uid) > 0) {
      $deploy_data['uid'] = $this->configEntity->uid;
      if (strlen($this->configEntity->gid) > 0) {
        $deploy_data['gid'] = $this->configEntity->gid;
      }
    }

    return $deploy_data;
  }

  /**
   * Format an array of build data ready to pass to OpenShift.
   *
   * @todo - move this into the client?
   *
   * @param string $source_ref
   *   The source tag/branch/commit.
   * @param string $source_repo
   *   The source repository.
   * @param string $builder_image
   *   The builder image.
   * @param array $formatted_env_vars
   *   Environment variables.
   *
   * @return array
   *   Build data.
   */
  protected function formatBuildData(string $source_ref, string $source_repo, string $builder_image, array $formatted_env_vars = []) {
    // Package config for the client.
    return [
      'git' => [
        'ref' => $source_ref,
        'uri' => $source_repo,
      ],
      'source' => [
        'type' => 'DockerImage',
        'name' => $builder_image,
      ],
      'env_vars' => $formatted_env_vars,
    ];
  }

  /**
   * Attempt to create PVC's for the deployment in OpenShift.
   *
   * PVC's that already exist will not be created/updated.
   *
   * @todo - move this into the client?
   * @todo - make storage size configurable
   *
   * @param string $project_name
   *   The name of the project being deployed.
   * @param string $deployment_name
   *   The name of the deployment being created.
   * @param string $storage_class
   *   Optional storage class name.
   * @param array $secrets
   *   Optional secrets to attach.
   *
   * @return array|bool
   *   The volume config array, or false if creating PVCs was unsuccessful.
   * @throws \UniversityOfAdelaide\OpenShift\ClientException
   */
  protected function setupVolumes(string $project_name, string $deployment_name, $storage_class = '', $secrets = []) {
    $volumes = $this->generateVolumeData($project_name, $deployment_name);

    try {
      if (!$this->client->getPersistentVolumeClaim($volumes['shared']['name'])) {
        $this->client->createPersistentVolumeClaim(
          $volumes['shared']['name'],
          'ReadWriteMany',
          '5Gi',
          $deployment_name,
          $storage_class
        );
      }
      // Only job containers have access to the backup pvc.
      if (isset($volumes['backup']) && !$this->client->getPersistentVolumeClaim($volumes['backup']['name'])) {
        $this->client->createPersistentVolumeClaim(
          $volumes['backup']['name'],
          'ReadWriteMany',
          '5Gi',
          $deployment_name,
          $storage_class
        );
      }
    }
    catch (ClientException $e) {
      $this->handleClientException($e);
      return FALSE;
    }

    // Append project and environment specific secrets to the volumes.
    if (count($secrets)) {
      if (array_key_exists('environment', $secrets)) {
        $volumes['secret-environment'] = [
          'name' => 'secret-environment',
          'path' => '/etc/secret-environment',
          'type' => 'secret',
          'secret' => $secrets['environment'],
        ];
      }
      if (array_key_exists('project', $secrets)) {
        $volumes['secret-project'] = [
          'name' => 'secret-project',
          'path' => '/etc/secret-project',
          'type' => 'secret',
          'secret' => $secrets['project'],
        ];
      }
    }

    return $volumes;
  }

  /**
   * Generates the volume data for deployment configuration.
   *
   * @param string $project_name
   *   The name of the project in OpenShift.
   * @param string $deployment_name
   *   The name of the deployment.
   * @param bool $mount_backup
   *   Add the backup mount to the volumes, should only used by jobs.
   *
   * @return array
   *   Volume data.
   *
   * @throws \UniversityOfAdelaide\OpenShift\ClientException
   */
  protected function generateVolumeData(string $project_name, string $deployment_name, bool $mount_backup = FALSE) {
    $shared_pvc_name = $deployment_name . '-shared';
    // @todo This should be project_name-backup or similar - one backup pv per project.
    $backup_pvc_name = self::sanitise($project_name) . '-backup';

    $volumes = [
      'shared' => [
        'type' => 'pvc',
        'name' => $shared_pvc_name,
        'path' => '/shared',
      ],
    ];

    if ($mount_backup) {
      $volumes['backup'] = [
        'type' => 'pvc',
        'name' => $backup_pvc_name,
        'path' => '/backup',
      ];
    }

    // If a secret with the same name as the deployment exists, volume it in.
    if ($this->client->getSecret($deployment_name)) {
      // @todo Consider allowing parameters for secret volume path. Is there a convention?
      $volumes['secret'] = [
        'type' => 'secret',
        'name' => $deployment_name . '-secret',
        'path' => '/etc/secret',
        'secret' => $deployment_name,
      ];
    }

    return $volumes;
  }

  /**
   * Handles OpenShift ClientExceptions.
   *
   * @param \UniversityOfAdelaide\OpenShift\ClientException $exception
   *   The exception to be handled.
   */
  protected function handleClientException(ClientException $exception) {
    $reason = $exception->getMessage();
    if (strstr($exception->getBody(), 'Unauthorized')) {
      $reason = $this->t('Client is not authorized to access requested resource.');
    }

    \Drupal::logger('shp_orchestration')->error('An error occurred while communicating with OpenShift. %reason', [
      '%reason' => $reason,
    ]);

    // @todo Add handlers for other reasons for failure. Add as required.
    $this->messenger->addError(t("An error occurred while communicating with OpenShift. %reason", ['%reason' => $reason]));
  }

  /**
   * Create cron jobs.
   *
   * @param string $deployment_name
   *   Deployment identifier.
   * @param string $source_ref
   *   Image stream git ref.
   * @param bool $cron_suspended
   *   Is cron suspended?
   * @param array $cron_jobs
   *   The jobs to run.
   * @param array $image_stream
   *   Image stream.
   * @param array $volumes
   *   Volumes to mount.
   * @param array $deploy_data
   *   Deploy data.
   *
   * @return bool
   *   True on success.
   */
  protected function createCronJobs(string $deployment_name, string $source_ref, bool $cron_suspended, array $cron_jobs, array $image_stream, array $volumes, array $deploy_data) {
    $sanitised_source_ref = self::sanitise($source_ref);
    foreach ($cron_jobs as $cron_job) {
      $args_array = [
        '/bin/sh',
        '-c',
        $cron_job['cmd'],
      ];
      try {
        $this->client->createCronJob(
          $deployment_name . '-' . $this->stringGenerator->generateRandomString(5),
          $image_stream['status']['dockerImageRepository'] . ':' . $sanitised_source_ref,
          $cron_job['schedule'],
          $cron_suspended,
          $args_array,
          $volumes,
          $deploy_data
        );
      }
      catch (ClientException $e) {
        $this->handleClientException($e);
        return FALSE;
      }
    }
    return TRUE;
  }

}
