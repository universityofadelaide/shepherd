<?php

namespace Drupal\shp_orchestration\Plugin\OrchestrationProvider;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\shp_orchestration\OrchestrationProviderBase;
use UniversityOfAdelaide\OpenShift\Client as OpenShiftClient;

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

    $image_stream = $this->client->createImageStream($sanitised_name);
    if (!$image_stream) {
      // @todo Handle bad response.
      return FALSE;
    }

    // Kick off build of default source ref - this is purely for optimisation.
    $build_config = $this->client->createBuildConfig(
      $sanitised_name . '-' . $source_ref,
      $source_secret,
      $sanitised_name . ':' . $source_ref,
      $build_data
    );
    if (!$build_config) {
      // @todo Handle bad response.
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

    $build_config = $this->client->updateBuildConfig(
      $sanitised_name . '-' . $source_ref,
      $source_secret,
      $sanitised_name . ':' . $source_ref,
      $build_data
    );
    if (!$build_config) {
      // @todo Handle bad response.
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
    string $site_name,
    string $environment_name,
    string $environment_id,
    string $builder_image,
    string $source_repo,
    string $source_ref = 'master',
    string $source_secret = NULL
  ) {
    $sanitised_distribution_name = self::sanitise($distribution_name);
    $deployment_name = self::generateDeploymentName(
      $distribution_name,
      $site_name,
      $environment_name,
      $environment_id
    );
    $image_stream_tag = $sanitised_distribution_name . ':' . $source_ref;
    $build_config_name = $sanitised_distribution_name . '-' . $source_ref;

    // Create build config if it doesn't exist.
    $build_config = $this->client->getBuildConfig($build_config_name);
    if (!$build_config) {
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

      $build_config = $this->client->createBuildConfig(
        $build_config_name,
        $source_secret,
        $image_stream_tag,
        $build_data
      );
      if (!$build_config) {
        // @todo Handle bad response.
        return FALSE;
      }
    }

    // @todo Consider building this array by calling a hook.
    $deploy_config_env_vars = [];
    $env_vars_from_secrets = [
      'DATABASE_HOST',
      'DATABASE_PORT',
      'DATABASE_NAME',
      'DATABASE_USER',
    ];
    foreach ($env_vars_from_secrets as $env_var) {
      $deploy_config_env_vars[] = [
        'name' => $env_var,
        'valueFrom' => [
          'secretKeyRef' => [
            'key' => $env_var,
            'name' => $deployment_name,
          ],
        ],
      ];
    }
    $deploy_config_env_vars[] = [
      'name' => 'DATABASE_PASSWORD_FILE',
      'value' => '/etc/secret/DATABASE_PASSWORD',
    ];

    // @todo Parametrise storage size.
    $public_pvc_name = $deployment_name . '-public';
    $public_pvc_response = $this->client->createPersistentVolumeClaim(
      $public_pvc_name,
      'ReadWriteMany',
      '10Gi'
    );

    $private_pvc_name = $deployment_name . '-private';
    $private_pvc_response = $this->client->createPersistentVolumeClaim(
      $private_pvc_name,
      'ReadWriteMany',
      '10Gi'
    );

    if (!$public_pvc_response || !$private_pvc_response) {
      // @todo Handle bad response.
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
      'env_vars' => $deploy_config_env_vars,
      'volumes' => $volumes,
    ];

    $deployment_config = $this->client->createDeploymentConfig(
      $deployment_name,
      $image_stream_tag,
      $sanitised_distribution_name,
      $deploy_data
    );
    if (!$deployment_config) {
      // @todo Handle bad response.
      return FALSE;
    }

    // Create a service.
    // @todo - make port a var and great .. so great .. yuge!
    $service_data = [
      'port' => 8080,
      'targetPort' => 8080,
      'deployment' => $deployment_name,
    ];
    $service = $this->client->createService($deployment_name, $service_data);
    if (!$service) {
      // @todo Handle bad response.
      return FALSE;
    }

    $route = $this->client->createRoute($deployment_name, $deployment_name, '');
    if (!$route) {
      // @todo Handle bad response.
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function updatedEnvironment() {
    // TODO: Implement updateEnvironment() method.
  }

  /**
   * {@inheritdoc}
   */
  public function deletedEnvironment(
    string $distribution_name,
    string $site_name,
    string $environment_name,
    string $environment_id
  ) {
    $deployment_name = self::generateDeploymentName(
      $distribution_name,
      $site_name,
      $environment_name,
      $environment_id
    );

    $this->client->deleteDeploymentConfig($deployment_name);
    $this->client->deletePersistentVolumeClaim($deployment_name . '-public');
    $this->client->deletePersistentVolumeClaim($deployment_name . '-private');
    $this->client->deleteRoute($deployment_name);
    $this->client->deleteService($deployment_name);

    // TODO: // Check calls succeed.
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
    $secret = $this->client->getSecret($name);
    if (is_array($secret) && array_key_exists('data', $secret)) {
      if ($key) {
        return array_key_exists($key, $secret['data']) ? base64_decode($secret['data'][$key]) : FALSE;
      }
      return array_walk($secret['data'], 'base64_decode');
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function createSecret(string $name, array $data) {
    // Simply pass through to the client.
    return $this->client->createSecret($name, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function updateSecret(string $name, array $data) {
    // Simply pass through to the client.
    return $this->client->updateSecret($name, $data);
  }

  /**
   * {@inheritdoc}
   */
  public static function generateDeploymentName(
    string $distribution_name,
    string $site_name,
    string $environment_name,
    string $environment_id
  ) {
    return implode('-', [
      self::sanitise($distribution_name),
      self::sanitise($site_name),
      self::sanitise($environment_name),
      $environment_id,
    ]);
  }

}
