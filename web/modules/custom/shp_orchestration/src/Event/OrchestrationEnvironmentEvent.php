<?php

namespace Drupal\shp_orchestration\Event;

use Symfony\Component\EventDispatcher\Event;

class OrchestrationEnvironmentEvent extends Event {

  /**
   * The orchestration provider object
   *
   * @var object
   */
  protected $orchestrationProvider;

  /**
   * The deployment name
   *
   * @var string
   */
  protected $deploymentName;

  /**
   * The deployed environment
   *
   * @var string
   */
  protected $environment;

  /**
   * Storage to pass env vars around
   */
  protected $environmentVariables;

  /**
   * Constructs a Orchestration deployment event object.
   *
   * @param $orchestrationProvider
   *   The orchestration provider
   * @param $deploymentName
   *   The deployment name.
   * @param $environment
   *   The environment that was created.
   *
   * @internal param $ @var \UniversityOfAdelaide\OpenShift\Client $client
   *   The openshift client.
   */
  public function __construct($orchestrationProvider, $deploymentName, $environment) {
    $this->orchestrationProvider = $orchestrationProvider;
    $this->deploymentName = $deploymentName;
    $this->environment = $environment;
  }

  public function getOrchestrationProvider() {
    return $this->orchestrationProvider;
  }

  /**
   * Get the deployment name
   *
   * @return string
   *   The deployment name.
   */
  public function getDeploymentName() {
    return $this->deploymentName;
  }

  /**
   * Get the deployed environment
   *
   * @return array
   *   The environment.
   */
  public function getEnvironment() {
    return $this->environment;
  }

  /**
   * Set the environment variables
   *
   * @param array $environment_variables
   *   Environment variable settings to update.
   *
   * @return array
   */
  public function setEnvironmentVariables(array $environment_variables) {
    return $this->environmentVariables = $environment_variables;
  }

  /**
   * Get the environment variables
   */
  public function getEnvironmentVariables() {
    return $this->environmentVariables;
  }
}
