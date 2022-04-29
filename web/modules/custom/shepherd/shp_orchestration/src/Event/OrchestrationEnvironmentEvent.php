<?php

namespace Drupal\shp_orchestration\Event;

use Drupal\node\NodeInterface;
use Drupal\shp_orchestration\OrchestrationProviderInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event fired during orchestration of an environment.
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
   * The site this environment is for.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $site;

  /**
   * The environment record.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $environment;

  /**
   * The project for this environment.
   *
   * @var \Drupal\node\NodeInterface
   */
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
   * @param \Drupal\node\NodeInterface $site
   *   The site this environment is for.
   * @param \Drupal\node\NodeInterface $environment
   *   The environment record.
   * @param \Drupal\node\NodeInterface $project
   *   The project for this environment.
   */
  public function __construct(OrchestrationProviderInterface $orchestrationProvider, string $deploymentName, NodeInterface $site = NULL, NodeInterface $environment = NULL, NodeInterface $project = NULL) {
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

  /**
   * Get the site.
   *
   * @return \Drupal\node\NodeInterface
   *   Site node.
   */
  public function getSite() {
    return $this->site;
  }

  /**
   * Get the environment record.
   *
   * @return \Drupal\node\NodeInterface
   *   Environment node.
   */
  public function getEnvironment() {
    return $this->environment;
  }

  /**
   * Get the project for this environment.
   *
   * @return \Drupal\node\NodeInterface
   *   Project node.
   */
  public function getProject() {
    return $this->project;
  }

}
