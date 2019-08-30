<?php

namespace Drupal\shp_cache_backend\Plugin;

use Drupal\Core\Plugin\PluginBase;
use Drupal\node\NodeInterface;

/**
 * Defines an interface for CacheBackend plugins.
 */
abstract class CacheBackendBase extends PluginBase implements CacheBackendInterface {

  /**
   * {@inheritdoc}
   */
  public function getEnvironmentVariables(NodeInterface $environment) {
    return [];
  }

}
