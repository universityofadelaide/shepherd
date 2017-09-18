<?php

namespace Drupal\shp_orchestration\Event;

use Drupal\shp_orchestration\OrchestrationProviderInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class OrchestrationEnvironmentEvent.
 */
class OrchestrationEnvironmentEvent extends Event {

  /**
   * The orchestration provider object.
   *
   * @var \Drupal\shp_orchestration\OrchestrationProviderInterface
   */
  protected $orchestrationProvider;

  /**
   * The deployment name.
   *
   * @var string
   */
  protected $deploymentName;

  /**
   * Storage to pass env vars around.
   *
   * @var array
   */
  protected $environmentVariables;

  /**
   * Constructs a Orchestration deployment event object.
   *
   * @param \Drupal\shp_orchestration\OrchestrationProviderInterface $orchestrationProvider
   *   The orchestration provider instance.
   * @param string $deploymentName
   *   The deployment name.
   */
  public function __construct(OrchestrationProviderInterface $orchestrationProvider, string $deploymentName) {
    $this->orchestrationProvider = $orchestrationProvider;
    $this->deploymentName = $deploymentName;
  }

  /**
   * Get the orchestration provider.
   *
   * @return \Drupal\shp_orchestration\OrchestrationProviderInterface
   *   The orchestration provider.
   */
  public function getOrchestrationProvider() {
    return $this->orchestrationProvider;
  }

  /**
   * Get the deployment name.
   *
   * @return string
   *   The deployment name.
   */
  public function getDeploymentName() {
    return $this->deploymentName;
  }

  /**
   * Set the environment variables.
   *
   * @param array $environment_variables
   *   Environment variable settings to update.
   */
  public function setEnvironmentVariables(array $environment_variables) {
    $this->environmentVariables = $environment_variables;
  }

  /**
   * Get the environment variables.
   *
   * @return array
   *   An array of environment variables.
   */
  public function getEnvironmentVariables() {
    return $this->environmentVariables;
  }

}
