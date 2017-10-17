<?php

namespace Drupal\shp_orchestration\Service;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\node\NodeInterface;
use Drupal\shp_custom\Service\Environment;
use Drupal\shp_custom\Service\Site;

/**
 * Class Configuration.
 *
 * @package Drupal\shp_orchestration\Service
 */
class Configuration {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The environment service.
   *
   * @var \Drupal\shp_custom\Service\Environment
   */
  protected $environment;

  /**
   * The site service.
   *
   * @var \Drupal\shp_custom\Service\Site
   */
  protected $site;

  /**
   * Configuration constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The Drupal module handler service.
   * @param \Drupal\shp_custom\Service\Environment $environment
   *   The environment service.
   * @param \Drupal\shp_custom\Service\Site $site
   *   The site service.
   */
  public function __construct(ModuleHandlerInterface $moduleHandler, Environment $environment, Site $site) {
    $this->moduleHandler = $moduleHandler;
    $this->environment = $environment;
    $this->site = $site;
  }

  /**
   * Collate all the environment variables.
   *
   * Modules may provide environment variables using hook_shp_env_vars().
   * The order of precedence is as follows:
   * 1. Environment defined variables.
   * 2. Project defined variables.
   * 3. Module defined variables.
   *
   * I.e. Variables attached to an environment will override any other
   * definition.
   *
   * @param \Drupal\node\NodeInterface $node
   *   An environment node.
   *
   * @return array
   *   Env vars.
   */
  public function getEnvironmentVariables(NodeInterface $node) {
    $env_vars = $this->moduleHandler->invokeAll('shp_env_vars', [$node]);

    // Get the project via the site.
    $site = $this->environment->getSite($node);
    $project = $this->site->getProject($site);

    // Merge default environment variables from project.
    foreach ($project->field_shp_env_vars->getValue() as $env_var) {
      $env_vars[$env_var['key']] = $env_var['value'];
    }

    // Append custom environment variables from environment.
    foreach ($node->field_shp_env_vars->getValue() as $env_var) {
      $env_vars[$env_var['key']] = $env_var['value'];
    }

    return $env_vars;
  }

  /**
   * Collate all the secrets.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The environment node.
   *
   * @return array
   *   An array of secrets.
   */
  public function getSecrets(NodeInterface $node) {
    return $this->moduleHandler->invokeAll('shp_secrets', [$node]);
  }

}
