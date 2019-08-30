<?php

namespace Drupal\shp_cache_backend\Plugin\CacheBackend;

use Drupal\node\NodeInterface;
use Drupal\shp_cache_backend\Plugin\CacheBackendBase;

/**
 * Provides Redis integration.
 *
 * @CacheBackend(
 *   id = "memcached",
 *   label = @Translation("Memcached")
 * )
 */
class Memcached extends CacheBackendBase {

  /**
   * {@inheritdoc}
   */
  public function onEnvironmentCreate(NodeInterface $environment) {
    // TODO: Implement onEnvironmentCreate() method.
  }

  /**
   * {@inheritdoc}
   */
  public function onEnvironmentDelete(NodeInterface $environment) {
    // TODO: Implement onEnvironmentDelete() method.
  }

}
