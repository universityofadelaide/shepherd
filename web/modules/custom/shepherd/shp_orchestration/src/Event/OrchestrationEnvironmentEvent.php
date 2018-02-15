<?php

namespace Drupal\shp_orchestration\Event;

use Drupal\node\Entity\Node;
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
   * The site this environment is for.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $site;

  /**
   * The environment record.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $environment;

  /**
   * The project for this environment.
   *
   * @var \Drupal\node\Entity\Node
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
   * @param \Drupal\node\Entity\Node $site
   *   The site this environment is for.
   * @param \Drupal\node\Entity\Node $environment
   *   The environment record.
   * @param \Drupal\node\Entity\Node $project
   *   The project for this environment.
   */
  public function __construct(OrchestrationProviderInterface $orchestrationProvider, string $deploymentName, Node $site = NULL, Node $environment = NULL, Node $project = NULL) {
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
   * @return \Drupal\node\Entity\Node
   *   Site node.
   */
  public function getSite() {
    return $this->site;
  }

  /**
   * Get the environment record.
   *
   * @return \Drupal\node\Entity\Node
   *   Environment node.
   */
  public function getEnvironment() {
    return $this->environment;
  }

  /**
   * Get the project for this environment.
   *
   * @return \Drupal\node\Entity\Node
   *   Project node.
   */
  public function getProject() {
    return $this->project;
  }
}
