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
  private function sanitise($text) {
    return strtolower(preg_replace('/\s+/', '-', $text));
  }

  /**
   * {@inheritdoc}
   */
  public function createdDistribution(string $name, string $builder_image, string $source_repo, string $source_ref = 'master', string $source_secret = NULL) {
    $sanitised_name = $this->sanitise($name);

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
    $sanitised_name = $this->sanitise($name);

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
    $sanitised_distribution_name = $this->sanitise($distribution_name);
    $sanitised_site_name = $this->sanitise($site_name);
    $sanitised_environment_name = $this->sanitise($environment_name);
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
    // TODO: Create new database for the environment.
    // TODO: Build up env vars for database config.
    $deploy_config_env_vars = [];

    $deployment_name = implode('-', [
      $sanitised_distribution_name,
      $sanitised_site_name,
      $sanitised_environment_name,
      $environment_id,
    ]);

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

    $deploy_data = [
      'containerPort' => 8080,
      'memory_limit' => '128Mi',
      'env_vars' => $deploy_config_env_vars,
      'public_volume' => $public_pvc_name,
      'private_volume' => $private_pvc_name,
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
  public function deletedEnvironment() {
    // TODO: Implement deleteEnvironment() method.
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
  public function getSecret(string $name) {
    return $this->client->getSecret($name);
  }

}
