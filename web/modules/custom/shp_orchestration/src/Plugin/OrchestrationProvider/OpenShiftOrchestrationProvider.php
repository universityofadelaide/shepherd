<?php

namespace Drupal\shp_orchestration\Plugin\OrchestrationProvider;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\shp_orchestration\Event\OrchestrationEnvironmentEvent;
use Drupal\shp_orchestration\Event\OrchestrationEvents;
use Drupal\shp_orchestration\OrchestrationProviderBase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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
   * Default git ref.
   *
   * @var string
   */
  public $defaultGitRef = 'master';

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $event_dispatcher);

    $this->client = new OpenShiftClient(
      $this->configEntity->endpoint,
      $this->configEntity->token,
      $this->configEntity->namespace,
      $this->configEntity->verify_tls
    );
  }

  /**
   * Converts a string into a format acceptable for OpenShift.
   *
   * @param string $text
   *   The title to be sanitised.
   *
   * @return string
   *   sanitised title.
   */
  private static function sanitise($text) {
    return strtolower(preg_replace('/\s+/', '-', $text));
  }

  /**
   * {@inheritdoc}
   */
  public function createdDistribution(string $name, string $builder_image, string $source_repo, string $source_ref = 'master', string $source_secret = NULL) {
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
      $image_stream = $this->client->generateImageStream($sanitised_name);
      $this->client->createImageStream($image_stream);
      $this->client->createBuildConfig(
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
  public function updatedDistribution(string $name, string $builder_image, string $source_repo, string $source_ref = 'master', string $source_secret = NULL) {
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
  public function deletedDistribution($name) {
    // @todo Implement deletedDistribution() method.
  }

  /**
   * {@inheritdoc}
   */
  public function createdEnvironment(
    string $distribution_name,
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
    array $cron_jobs = []
  ) {
    $sanitised_distribution_name = self::sanitise($distribution_name);
    $deployment_name = self::generateDeploymentName(
      $distribution_name,
      $short_name,
      $environment_id
    );
    $image_stream_tag = $sanitised_distribution_name . ':' . $source_ref;
    $build_config_name = $sanitised_distribution_name . '-' . $source_ref;

    // Create build config if it doesn't exist.
    if (!$this->client->getBuildConfig($build_config_name)) {
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
        $this->client->createBuildConfig(
          $build_config_name,
          $source_secret,
          $image_stream_tag,
          $build_data
        );
      }
      catch (ClientException $e) {
        $this->handleClientException($e);
        return FALSE;
      }
    }

    $formatted_env_vars = $this->formatEnvVars($environment_variables, $deployment_name);
    if (!$volumes = $this->setupVolumes($distribution_name, $deployment_name, TRUE)) {
      return FALSE;
    }

    $deploy_data = $this->formatDeployData(
      $deployment_name,
      $formatted_env_vars,
      $environment_url,
      $site_id,
      $environment_id
    );

    // If set, add uid and gid from config to deploy data.
    if (strlen($this->configEntity->uid) > 0) {
      $deploy_data['uid'] = $this->configEntity->uid;
      if (strlen($this->configEntity->gid) > 0) {
        $deploy_data['gid'] = $this->configEntity->gid;
      }
    }

    $deployment_config = $this->client->generateDeploymentConfig(
      $deployment_name,
      $image_stream_tag,
      $sanitised_distribution_name,
      $volumes,
      $deploy_data
    );

    try {
      $this->client->createDeploymentConfig($deployment_config);
    }
    catch (ClientException $e) {
      $this->handleClientException($e);
      return FALSE;
    }

    // Allow other modules to react to the Environment creation.
    $event = new OrchestrationEnvironmentEvent($this->client, $deployment_config);
    $this->eventDispatcher->dispatch(OrchestrationEvents::CREATED_ENVIRONMENT, $event);

    $image_stream = $this->client->getImageStream($sanitised_distribution_name);
    if ($image_stream) {
      foreach ($cron_jobs as $schedule => $args) {
        $args_array = [
          '/bin/sh',
          '-c',
          $args,
        ];
        try {
          // @todo - label support needs to be added to the delete, and then creation of cron jobs
          //$this->client->createCronJob(
          //  $deployment_name . '-' . \Drupal::service('shp_custom.random_string')->generate(5),
          //  $image_stream['status']['dockerImageRepository'] . ':' . $source_ref,
          //  $schedule,
          //  $args_array,
          //  $volumes,
          //  $deploy_data
          //);
        }
        catch (ClientException $e) {
          $this->handleClientException($e);
          return FALSE;
        }
      }
    }

    // @todo - make port a var and great .. so great .. yuge!
    $port = 8080;
    try {
      $this->client->createService($deployment_name, $deployment_name, $port, $port);
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
    string $distribution_name,
    string $short_name,
    string $site_id,
    string $environment_id,
    string $environment_url,
    string $builder_image,
    string $source_repo,
    string $source_ref = 'master',
    string $source_secret = NULL,
    array $cron_jobs = []
  ) {

  }

  /**
   * {@inheritdoc}
   */
  public function deletedEnvironment(
    string $distribution_name,
    string $short_name,
    string $environment_id
  ) {
    $deployment_name = self::generateDeploymentName(
      $distribution_name,
      $short_name,
      $environment_id
    );

    // Allow other modules to react to the Environment deletion.
    $deployment_config = $this->client->getDeploymentConfig($deployment_name);
    $event = new OrchestrationEnvironmentEvent($this->client,$deployment_config);
    $this->eventDispatcher->dispatch(OrchestrationEvents::DELETED_ENVIRONMENT, $event);

    try {
      // Scale the pods to zero, then delete the pod creators.
      //$this->client->updateDeploymentConfig($deployment_name, 0);
      //$this->client->updateReplicationControllers('', 'app=' . $deployment_name, 0);

      // Not sure if we need to delay a little here, do the cronjob and routes
      // to artificially delay.
      // @todo - label support needs to be added to the delete, and then creation of cron jobs
      //$this->client->deleteCronJob($deployment_name);
      $this->client->deleteRoute($deployment_name);
      $this->client->deleteService($deployment_name);

      $this->client->deleteDeploymentConfig($deployment_name);
      //$this->client->deleteReplicationControllers('', 'app=' . $deployment_name);

      // Now the things not in the typically visible ui.
      $this->client->deletePersistentVolumeClaim($deployment_name . '-shared');
      $this->client->deleteSecret($deployment_name);
    }
    catch (ClientException $e) {
      $this->handleClientException($e);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function archivedEnvironment(
    int $environment_id
  ) {
    $site = Node::load($environment_id->field_shp_site->target_id);
    $distribution = Node::load($site->field_shp_distribution->target_id);

    self::deletedEnvironment(
      $distribution->title->value,
      $site->field_shp_short_name->value,
      $environment_id
    );
  }

  /**
   * {@inheritdoc}
   */
  public function createdSite() {
    // TODO: Implement createSite() method.
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
  public function deletedSite() {
    // TODO: Implement deleteSite() method.
  }

  /**
   * {@inheritdoc}
   */
  public function backupEnvironment(
    string $distribution_name,
    string $short_name,
    string $environment_id,
    string $source_ref = 'master',
    string $commands = ''
  ) {
    return $this->executeJob(
      $distribution_name,
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
    string $distribution_name,
    string $short_name,
    string $environment_id,
    string $source_ref = 'master',
    string $commands = ''
  ) {
    return $this->executeJob(
      $distribution_name,
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
    string $distribution_name,
    string $short_name,
    string $environment_id,
    string $source_ref = 'master',
    string $commands = ''
  ) {
    $sanitised_distribution_name = self::sanitise($distribution_name);
    $deployment_name = self::generateDeploymentName(
      $distribution_name,
      $short_name,
      $environment_id
    );

    // Retrieve existing deployment details to use where possible.
    $deployment_config = $this->client->getDeploymentConfig($deployment_name);

    $image_stream = $this->client->getImageStream($sanitised_distribution_name);
    $volumes = $this->setupVolumes($distribution_name, $deployment_name);
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
  public static function generateDeploymentName(
    string $distribution_name,
    string $short_name,
    string $environment_id
  ) {
    return implode('-', [
      self::sanitise($distribution_name),
      self::sanitise($short_name),
      $environment_id,
    ]);
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
  public function getEnvironmentUrl(string $distribution_name, string $short_name, string $environment_id) {

    $deployment_name = self::generateDeploymentName(
      $distribution_name,
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
   * Format an array of environment variables ready to pass to OpenShift.
   *
   * @param array $environment_variables
   *   An array of environment variables to be set for the pod.
   * @param string $deployment_name
   *   The deployment name.
   *
   * @return array
   *   The env var config array.
   */
  private function formatEnvVars(array $environment_variables, string $deployment_name) {
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
      ],
    ];

    return $deploy_data;
  }

  /**
   * Format an array of volume data ready to pass to OpenShift.
   *
   * @param string $distribution_name
   *   The name of the distribution being deployed.
   * @param string $deployment_name
   *   The name of the deployment being created.
   * @param bool $setup
   *   Whether to configure shared files and backup PVCs.
   *
   * @return array|bool
   *   The volume config array, or false if creating PVCs was unsuccessful.
   */
  private function setupVolumes(string $distribution_name, string $deployment_name, bool $setup = FALSE) {
    $shared_pvc_name = $deployment_name . '-shared';
    // @todo This should be dist_name-backup or similar - one backup pv per distro.
    $backup_pvc_name = self::sanitise($distribution_name) . '-backup';

    if ($setup) {
      try {
        if (!$this->client->getPersistentVolumeClaim($shared_pvc_name)) {
          $this->client->createPersistentVolumeClaim(
            $shared_pvc_name,
            'ReadWriteMany',
            '5Gi'
          );
        }
        if (!$this->client->getPersistentVolumeClaim($backup_pvc_name)) {
          $this->client->createPersistentVolumeClaim(
            $backup_pvc_name,
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

    $volumes = [
      [
        'type' => 'pvc',
        'name' => $shared_pvc_name,
        'path' => '/shared',
      ],
      [
        'type' => 'pvc',
        'name' => $backup_pvc_name,
        'path' => '/backup',
      ],
    ];

    // If a secret with the same name as the deployment exists, volume it in.
    if ($this->client->getSecret($deployment_name)) {
      // @todo Consider allowing parameters for secret volume path. Is there a convention?
      $volumes[] = [
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
      $reason = t('Client is not authorized to access requested resource.');
    }
    // @todo Add handlers for other reasons for failure. Add as required.
    drupal_set_message(t("An error occurred while communicating with OpenShift. %reason", ['%reason' => $reason]), 'error');
  }

}
