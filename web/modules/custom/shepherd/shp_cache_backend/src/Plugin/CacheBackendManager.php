<?php

namespace Drupal\shp_cache_backend\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides the CacheBackend plugin manager.
 */
class CacheBackendManager extends DefaultPluginManager {

  /**
   * Constructor for CacheBackendManager objects.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/CacheBackend', $namespaces, $module_handler, 'Drupal\shp_cache_backend\Plugin\CacheBackendInterface', 'Drupal\shp_cache_backend\Annotation\CacheBackend');

    $this->alterInfo('shp_cache_backend_plugin_info');
    $this->setCacheBackend($cache_backend, 'shp_cache_backend_plugins');
  }

}
