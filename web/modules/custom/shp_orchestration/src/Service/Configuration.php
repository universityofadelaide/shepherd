<?php

namespace Drupal\shp_orchestration\Service;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\node\NodeInterface;

/**
 * Class Configuration.
 *
 * @package Drupal\shp_orchestration\Service
 */
class Configuration {

  protected $moduleHandler;

  /**
   * Configuration constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The Drupal module handler service.
   */
  public function __construct(ModuleHandlerInterface $moduleHandler) {
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * Collate all the environment variables.
   *
   * @param \Drupal\node\NodeInterface $node
   *   An environment node.
   *
   * @return array
   *   Env vars.
   */
  public function getEnvironmentVariables(NodeInterface $node) {
    return $this->moduleHandler->invokeAll('shp_env_vars', [$node]);
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
