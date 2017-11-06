<?php

namespace Drupal\shp_orchestration\Plugin\OrchestrationProvider;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\shp_orchestration\OrchestrationProviderBase;
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
   */
  protected $client;

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
    bool $update_on_image_change = FALSE,
    array $environment_variables = [],
    array $secrets = [],
    array $probes = [],
    array $cron_jobs = []
  ) {
    // @todo Refactor this. _The complexity is too damn high!_

    $sanitised_project_name = self::sanitise($project_name);
    $sanitised_source_ref = self::sanitise($source_ref);
    $deployment_name = self::generateDeploymentName(
      $project_name,
      $short_name,
      $environment_id
    );
    $image_stream_tag = $sanitised_project_name . ':' . $sanitised_source_ref;
    $build_config_name = $sanitised_project_name . '-' . $sanitised_source_ref;
    $formatted_env_vars = $this->formatEnvVars($environment_variables, $deployment_name);

    // Tell, don't ask (to create a build config).
    $this->createBuildConfig($build_config_name, $source_ref, $source_repo, $builder_image, $source_secret, $image_stream_tag, $formatted_env_vars);

    if (!$volumes = $this->setupVolumes($project_name, $deployment_name, TRUE)) {
      return FALSE;
    }

    $deploy_data = $this->formatDeployData(
      $deployment_name,
      $formatted_env_vars,
      $environment_url,
      $site_id,
      $environment_id
    );

    // @todo $update_on_image_change should be passed in as a parameter
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
    if ($image_stream) {
      foreach ($cron_jobs as $schedule => $args) {
        $args_array = [
          '/bin/sh',
          '-c',
          $args,
        ];
        try {
          $this->client->createCronJob(
            $deployment_name . '-' . \Drupal::service('shp_custom.random_string')->generate(5),
            $image_stream['status']['dockerImageRepository'] . ':' . $source_ref,
            $schedule,
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
    }

    if (!$update_on_image_change) {
      // We need to check if the image is already 'built', or we get an error.
      $build_status = $this->client->getBuilds('', 'buildconfig=' . $build_config_name);
      if (count($build_status['items']) && $build_status['items'][0]['status']['phase'] === 'Complete') {
        $this->client->instantiateDeploymentConfig($deployment_name);
      }
      else {
        drupal_set_message(t('Build not yet complete, manual triggering of deployment will be required.'));
      }
    }

    // @todo - make port a var and great .. so great .. yuge!
    $port = 8080;
    try {
      $this->client->createService($deployment_name, $deployment_name, $port, $port, $deployment_name);
      $this->client->createRoute($deployment_name, $deployment_name, $domain, $path);
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
    array $environment_variables = [],
    array $secrets = [],
    array $probes = [],
    array $cron_jobs = []
  ) {

  }

  /**
   * {@inheritdoc}
   */
  public function deletedEnvironment(
    string $project_name,
    string $short_name,
    string $environment_id
  ) {
    $deployment_name = self::generateDeploymentName(
      $project_name,
      $short_name,
      $environment_id
    );

    try {
      // @todo are we doing this?
      // Scale the pods to zero, then delete the pod creators.
      //$this->client->updateDeploymentConfig($deployment_name, 0);
      //$this->client->updateReplicationControllers('', 'app=' . $deployment_name, 0);

      $this->client->deleteCronJob('', 'app=' . $deployment_name);
      $this->client->deleteRoute($deployment_name);
      $this->client->deleteService($deployment_name);

      $this->client->deleteDeploymentConfig($deployment_name);
      // @todo remove this?
      //$this->client->deleteReplicationControllers('', 'app=' . $deployment_name);

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

    self::deletedEnvironment(
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
    string $source_ref = 'master',
    bool $clear_cache = TRUE
  ) {
    $site_deployment_name = self::generateDeploymentName(
      $project_name,
      $short_name,
      $site_id
    );

    $environment_deployment_name = self::generateDeploymentName(
      $project_name,
      $short_name,
      $environment_id
    );

    $result = $this->client->updateService($site_deployment_name, $environment_deployment_name);
    if ($result && $clear_cache) {
      self::executeJob(
        $project_name,
        $short_name,
        $environment_id,
        $source_ref,
        "drush -r web cr"
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
    $deployment_name = self::generateDeploymentName(
      $project_name,
      $short_name,
      $site_id
    );
    // @todo - make port a var and great .. so great .. yuge!
    $port = 8080;
    try {
      $this->client->createService($deployment_name, $deployment_name, $port, $port, $deployment_name);
      $this->client->createRoute($deployment_name, $deployment_name, $domain, $path);
    }
    catch (ClientException $e) {
      $this->handleClientException($e);
      return FALSE;
    }
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
    $deployment_name = self::generateDeploymentName(
      $project_name,
      $short_name,
      $site_id
    );

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
    $deployment_name = self::generateDeploymentName(
      $project_name,
      $short_name,
      $environment_id
    );

    // Retrieve existing deployment details to use where possible.
    $deployment_config = $this->client->getDeploymentConfig($deployment_name);

    $image_stream = $this->client->getImageStream($sanitised_project_name);
    $volumes = $this->setupVolumes($project_name, $deployment_name);
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
        $deployment_name . '-' . \Drupal::service('shp_custom.random_string')->generate(5),
        $image_stream['status']['dockerImageRepository'] . ':' . $source_ref,
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
      return $this->client->getDeploymentConfigs('site_id=' . $site_id);
    }
    catch (ClientException $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironmentUrl(string $project_name, string $short_name, string $environment_id) {
    $deployment_name = self::generateDeploymentName(
      $project_name,
      $short_name,
      $environment_id
    );

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
    $deployment_name = self::generateDeploymentName(
      $project_name,
      $short_name,
      $environment_id
    );

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
    $deployment_name = self::generateDeploymentName(
      $project_name,
      $short_name,
      $environment_id
    );

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
  private function generateOpenShiftPodUrl(string $pod_name, string $view) {

    $endpoint = $this->configEntity->endpoint;
    $namespace = $this->configEntity->namespace;
    $link = Url::fromUri($endpoint . '/console/project/' . $namespace . '/browse/pods/' . $pod_name, [
      'query' => [
        'tab' => $view,
      ],
    ],
    [
      'attributes' => [
        'target' => '_blank',
      ],
    ]
    );

    return $link;
  }

  /**
   * Format an array of environment variables ready to pass to OpenShift.
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
  private function formatEnvVars(array $environment_variables, string $deployment_name = '') {
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
  private function formatDeployData(string $name, array $formatted_env_vars, string $environment_url, int $site_id, int $environment_id) {
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
  private function formatBuildData(string $source_ref, string $source_repo, string $builder_image, array $formatted_env_vars = []) {
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
   * Format an array of volume data ready to pass to OpenShift.
   * @todo - move this into the client?
   *
   * @param string $project_name
   *   The name of the project being deployed.
   * @param string $deployment_name
   *   The name of the deployment being created.
   * @param bool $setup
   *   Whether to configure shared files and backup PVCs.
   *
   * @return array|bool
   *   The volume config array, or false if creating PVCs was unsuccessful.
   */
  private function setupVolumes(string $project_name, string $deployment_name, bool $setup = FALSE) {
    $volumes = $this->generateVolumeData($project_name, $deployment_name);

    if ($setup) {
      try {
        if (!$this->client->getPersistentVolumeClaim($volumes['shared']['name'])) {
          $this->client->createPersistentVolumeClaim(
            $volumes['shared']['name'],
            'ReadWriteMany',
            '5Gi'
          );
        }
        if (!$this->client->getPersistentVolumeClaim($volumes['backup']['name'])) {
          $this->client->createPersistentVolumeClaim(
            $volumes['backup']['name'],
            'ReadWriteMany',
            '5Gi'
          );
        }
      }
      catch (ClientException $e) {
        $this->handleClientException($e);
        return FALSE;
      }
    }

    return $volumes;
  }

  /**
   * Generates the volume data for deployment configuration.
   *
   * @param string $project_name
   *   The name of the project.
   * @param string $deployment_name
   *   The name of the deployment.
   *
   * @return array
   *   Volume data.
   */
  protected function generateVolumeData(string $project_name, string $deployment_name) {
    $shared_pvc_name = $deployment_name . '-shared';
    // @todo This should be project_name-backup or similar - one backup pv per project.
    $backup_pvc_name = self::sanitise($project_name) . '-backup';

    $volumes = [
      'shared' => [
        'type' => 'pvc',
        'name' => $shared_pvc_name,
        'path' => '/shared',
      ],
      'backup' => [
        'type' => 'pvc',
        'name' => $backup_pvc_name,
        'path' => '/backup',
      ],
    ];

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
  private function handleClientException(ClientException $exception) {
    $reason = $exception->getMessage();
    if (strstr($exception->getBody(), 'Unauthorized')) {
      $reason = $this->t('Client is not authorized to access requested resource.');
    }

    \Drupal::logger('shp_orchestration')->error('An error occurred while communicating with OpenShift. %reason', [
      '%reason' => $reason,
    ]);

    // @todo Add handlers for other reasons for failure. Add as required.
    drupal_set_message(t("An error occurred while communicating with OpenShift. %reason", ['%reason' => $reason]), 'error');
  }

}
