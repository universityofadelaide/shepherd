<?php

namespace Drupal\shp_orchestration\Event;

use Symfony\Component\EventDispatcher\Event;

class OrchestrationEnvironmentEvent extends Event {

  /**
   * The orchestration provider object
   *
   * @var \Drupal\shp_orchestration\OrchestrationProviderInterface
   */
  protected $orchestrationProvider;

  /**
   * The deployment name
   *
   * @var string
   */
  protected $deploymentName;

  /**
   * Storage to pass env vars around
   */
  protected $environmentVariables;

  /**
   * Constructs a Orchestration deployment event object.
   *
   * @param \Drupal\shp_orchestration\OrchestrationProviderInterface $orchestrationProvider
   *   The orchestration provider instance.
   * @param string $deploymentName
   *   The deployment name.
   *
   */
  public function __construct($orchestrationProvider, $deploymentName) {
    $this->orchestrationProvider = $orchestrationProvider;
    $this->deploymentName = $deploymentName;
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
