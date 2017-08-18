<?php

namespace Drupal\shp_orchestration\Event;

use Symfony\Component\EventDispatcher\Event;

class OrchestrationEnvironmentEvent extends Event {

  /**
   * The openshift client.
   *
   * @var \UniversityOfAdelaide\OpenShift\Client
   */
  protected $client;

  /**
   * DeploymentConfig.
   *
   * @var array
   */
  protected $deploymentConfig;

  /**
   * Constructs a Orchestration deployment event object.
   *
   * @param @var \UniversityOfAdelaide\OpenShift\Client $client
   *   The openshift client.
   * @param array $deploymentConfig
   *   The deployment config about to be executed.
   */
  public function __construct($client, $deploymentConfig) {
    $this->client = $client;
    $this->deploymentConfig = $deploymentConfig;
  }

  /**
   * Get the orchestration client.
   *
   */
  public function getClient() {
    return $this->client;
  }

  /**
   * Get the deployment config
   *
   * @return array
   *   The deployment config.
   */
  public function getDeploymentConfig() {
    return $this->deploymentConfig;
  }



}
