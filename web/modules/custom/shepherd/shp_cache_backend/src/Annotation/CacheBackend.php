<?php

namespace Drupal\shp_cache_backend\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Cache Backend annotation object.
 *
 * @see \Drupal\shp_cache_backend\Plugin\CacheBackendManager
 * @see plugin_api
 *
 * @Annotation
 */
class CacheBackend extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The status of the plugin.
   *
   * @var bool
   */
  public $status;

}
