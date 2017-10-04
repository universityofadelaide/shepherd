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
   * Objects related to an environment
   */
  protected $site;
  protected $environment;
  protected $project;

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
   * @param object $site
   *   The site this environment is for.
   * @param object $environment
   *   The environment record.
   * @param object $project
   *   The project for this environment.
   */
  public function __construct(OrchestrationProviderInterface $orchestrationProvider, string $deploymentName, $site = NULL, $environment = NULL, $project = NULL) {
    $this->orchestrationProvider = $orchestrationProvider;
    $this->deploymentName = $deploymentName;

    $this->site = $site;
    $this->environment = $environment;
    $this->project = $project;
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

  public function getSite() {
    return $this->site;
  }

  public function getEnvironment() {
    return $this->environment;
  }

  public function getProject() {
    return $this->project;
  }
}
