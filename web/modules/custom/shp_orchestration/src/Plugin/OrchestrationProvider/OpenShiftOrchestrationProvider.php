<?php

namespace Drupal\shp_orchestration\Plugin\OrchestrationProvider;

use Drupal\Core\Entity\EntityTypeManagerInterface;
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
   * Default git ref.
   *
   * @var string
   */
  public $defaultGitRef = 'master';

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);

    $this->client = new OpenShiftClient(
      $this->configEntity->endpoint,
      $this->configEntity->token,
      $this->configEntity->namespace,
      $this->configEntity->mode === 'dev'
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
      $this->client->createImageStream($sanitised_name);
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

    $public_pvc_name = $deployment_name . '-public';
    $private_pvc_name = $deployment_name . '-private';
    try {
      // @todo Parametrise storage size.
      $this->client->createPersistentVolumeClaim(
        $public_pvc_name,
        'ReadWriteMany',
        '10Gi'
      );

      $this->client->createPersistentVolumeClaim(
        $private_pvc_name,
        'ReadWriteMany',
        '10Gi'
      );

    }
    catch (ClientException $e) {
      $this->handleClientException($e);
      return FALSE;
    }

    // @todo Consider allowing parameters for volume paths. Are they set by the distro?
    $volumes = [
      [
        'type' => 'pvc',
        'name' => $public_pvc_name,
        'path' => '/code/web/sites/default/files',
      ],
      [
        'type' => 'pvc',
        'name' => $private_pvc_name,
        'path' => '/code/private',
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

    $deploy_data = [
      'containerPort' => 8080,
      'memory_limit' => '128Mi',
      'env_vars' => $formatted_env_vars,
      'annotations' => [
        'shepherdUrl' => $environment_url,
      ],
      'labels' => [
        'site_id' => $site_id,
        'environment_id' => $environment_id,
      ],
    ];

    try {
      $this->client->createDeploymentConfig(
        $deployment_name,
        $image_stream_tag,
        $sanitised_distribution_name,
        $volumes,
        $deploy_data
      );
    }
    catch (ClientException $e) {
      $this->handleClientException($e);
      return FALSE;
    }

    $image_stream = $this->client->getImageStream($sanitised_distribution_name);
    if ($image_stream) {
      foreach ($cron_jobs as $schedule => $args) {
        $args_array = [
          '/bin/sh',
          '-c',
          $args,
        ];
        try {
          $this->client->createCronJob(
            $deployment_name,
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

    // Create a service.
    // @todo - make port a var and great .. so great .. yuge!
    $service_data = [
      'port' => 8080,
      'targetPort' => 8080,
      'deployment' => $deployment_name,
    ];
    try {
      $this->client->createService($deployment_name, $service_data);
      $this->client->createRoute($deployment_name, $deployment_name, '');
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

    try {
      // Scale the pods to zero, then delete the pod creators.
      $this->client->updateDeploymentConfig($deployment_name, 0);
      $this->client->updateReplicationControllers('', 'openshift.io/deployment-config.name=' . $deployment_name, 0);

      // Not sure if we need to delay a little here, do the cronjob and routes
      // to artificially delay.
      $this->client->deleteCronJob($deployment_name);
      $this->client->deleteRoute($deployment_name);
      $this->client->deleteService($deployment_name);

      $this->client->deleteDeploymentConfig($deployment_name);
      $this->client->deleteReplicationControllers('', 'openshift.io/deployment-config.name=' . $deployment_name);

      // Now the things not in the typically visible ui.
      $this->client->deletePersistentVolumeClaim($deployment_name . '-public');
      $this->client->deletePersistentVolumeClaim($deployment_name . '-private');
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
      array_walk($secret['data'], 'base64_decode');
      return $secret['data'];
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
    drupal_set_message(t("An error occurred while communicating with OpenShift.") . ' ' . $reason, 'error');
  }

}
