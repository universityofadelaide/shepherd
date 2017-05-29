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

  protected $configurationStatus = TRUE;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);

    if (!is_object($this->configEntity)) {
      $this->configurationStatus = FALSE;
      return NULL;
    }

    // @todo - throw exception if configEntity is not set or incorrectly set.
    $devMode = FALSE;
    if ($this->configEntity->mode === "dev") {
      // Turn off SSL cert verification for development.
      $devMode = TRUE;
    }

    $this->client = new OpenShiftClient($this->configEntity->endpoint, $this->configEntity->token, $this->configEntity->namespace, $devMode);
  }

  private function getConfigurationStatus() {
    if (!$this->configurationStatus) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getClient() {
    if (!$this->getConfigurationStatus()) {
      return NULL;
    }

    return $this->client;
  }

  /**
   * Converts the title into a format acceptable for OpenShift.
   *
   * @param string $title
   * @return string Serialized title.
   */
  private function serializeTitle($title) {
    return strtolower(preg_replace('/\s+/', '-', $title));
  }

  /**
   * Converts back the title to Drupals format.
   *
   * @param string $title
   * @return string Deserialized title.
   */
  private function deserializeTitle($title) {
    // @todo - implement the method.
    return $title;
  }

  /**
   * {@inheritdoc}
   */
  public function createDistribution($name, array $data) {
    if (!$this->getConfigurationStatus()) {
      return NULL;
    }

    // Serialize the name.
    $name = $this->serializeTitle($name);

    // Test to see if there is a branch specified in the build_image.
    if (strpos($data['build_image'], ':') !== TRUE) {
      $data['build_image'] = $data['build_image'] . ':develop';
    }

    // Unpack the data.
    $build_data = [
      'git' => [
        'ref' => 'master',
        'uri' => $data['git']['uri']
      ],
      'source' => [
        'type' => 'DockerImage',
        'name' => $data['build_image']
      ],
    ];

    $image_stream_name = $name . '-stream';
    $build_config_name = $name;
    $secret = $data['secret'];
    // reference a secret.
    // to be able to build we need a preconfigured key.


    // create a imagestream
    $image_stream = $this->client->createImageStream($image_stream_name);

    // @todo - throw exceptions when any one of these client requests fail.
    if ($image_stream && $image_stream['body']) {
      // create the buildConfig
      $image_stream_name = $name . '-stream' . ':' . $build_data['git']['ref'];
      $buildConfig = $this->client->createBuildConfig($build_config_name, $secret, $image_stream_name, $build_data);
      // @todo - throw exceptions if buildConfig fails.
      if ($buildConfig && $buildConfig['body']) {
        return TRUE;
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function updateDistribution($name, array $data) {

    // Serialize the name
    $name = $this->serializeTitle($name);

    // Test to see if there is a branch specified in the build_image.
    if (strpos($data['build_image'], ':') !== TRUE) {
      $data['build_image'] = $data['build_image'] . ':develop';
    }

    // Unpack the data.
    $build_data = [
      'git' => [
        'ref' => 'master',
        'uri' => $data['git']['uri']
      ],
      'source' => [
        'type' => 'DockerImage',
        'name' => $data['build_image']
      ],
    ];

    $image_stream_name = $name . '-stream:' . $build_data['git']['ref'];
    $build_config_name = $name;
    $secret = $data['secret'];
    $buildConfig = $this->client->updateBuildConfig($build_config_name, $secret, $image_stream_name, $build_data);

    if ($buildConfig && $buildConfig['body']) {
      return TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDistribution($name) {
    // TODO: Implement getDistribution() method.
    $buildConfig = $this->client->getBuildConfig($name);

    if($buildConfig && $buildConfig['body']) {
      return $buildConfig;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteDistribution($name) {
    // TODO: Implement deleteDistribution() method.
  }

  /**
   * {@inheritdoc}
   */
  public function createEnvironment($name, array $data) {
    if (!$this->getConfigurationStatus()) {
      return NULL;
    }

    $name = $this->serializeTitle($name);

    $image_stream_tag = $data['imagestream']['tag'];
    $image_stream_name = $data['imagestream']['name'];

    // Check if the build already exists
    $request = $this->client->getBuildConfig($name);
    if (!$request['response']) {
      $request = $this->client->createBuildConfig(
        $name,
        $data['secret'],
        $image_stream_tag,
        $data
      );
      if (!$request) {
        // TODO: Error output
        return $request['response'];
      }
    }

    // TODO: Create new database for the environment


    // TODO: Build up env vars for database config
    $env_vars = [];

    // TODO: Allocate storage for public/private?!
    $public_volume = $name . '-public';
    $private_volume = $name . '-private';

    $deploy_data = [
      'containerPort' => 8080,
      'memory_limit' => '128Mi',
      'env_vars' => $env_vars,
      'public_volume' => $public_volume,
      'private_volume' => $private_volume,
    ];

    $request = $this->client->createDeploymentConfig(
      $name,
      $image_stream_tag,
      $image_stream_name,
      $deploy_data
    );

    return $request['response'];
  }

  /**
   * {@inheritdoc}
   */
  public function updateEnvironment() {
    // TODO: Implement updateEnvironment() method.
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironment() {
    // TODO: Implement getEnvironment() method.
  }

  /**
   * {@inheritdoc}
   */
  public function deleteEnvironment() {
    // TODO: Implement deleteEnvironment() method.
  }

  /**
   * {@inheritdoc}
   */
  public function createSite() {
    // TODO: Implement createSite() method.
  }

  /**
   * {@inheritdoc}
   */
  public function updateSite() {
    // TODO: Implement updateSite() method.
  }

  /**
   * {@inheritdoc}
   */
  public function getSite() {
    // TODO: Implement getSite() method.
  }

  /**
   * {@inheritdoc}
   */
  public function deleteSite() {
    // TODO: Implement deleteSite() method.
  }

  /**
   * {@inheritdoc}
   */
  public function getSecret($name) {
    if (!$this->getConfigurationStatus()) {
      return NULL;
    }

    return $this->client->getSecret($name);
  }

  /**
   * {@inheritdoc}
   */
  public function createSecret($name, array $data) {
    // TODO: Implement deleteSecret() method.
  }

  /**
   * {@inheritdoc}
   */
  public function updateSecret($name, array $data) {
    // TODO: Implement deleteSecret() method.
  }

  /**
   * {@inheritdoc}
   */
  public function deleteSecret($name) {
    // TODO: Implement deleteSecret() method.
  }
}
