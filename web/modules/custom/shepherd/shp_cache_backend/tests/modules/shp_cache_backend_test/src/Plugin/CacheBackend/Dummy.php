<?php

namespace Drupal\shp_cache_backend_test\Plugin\CacheBackend;

use Drupal\node\NodeInterface;
use Drupal\shp_cache_backend\Plugin\CacheBackendBase;

/**
 * Provides Dummy cache backend integration.
 *
 * @CacheBackend(
 *   id = "dummy",
 *   label = @Translation("Dummy")
 * )
 */
class Dummy extends CacheBackendBase {

  /**
   * {@inheritdoc}
   */
  public function onEnvironmentCreate(NodeInterface $environment) {
    // No-op.
  }

  /**
   * {@inheritdoc}
   */
  public function onEnvironmentDelete(NodeInterface $environment) {
    // No-op.
  }

}
