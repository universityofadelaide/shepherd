<?php

namespace Drupal\shp_orchestration\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a OrchestrationProvider annoation object.
 *
 * @see \Drupal\shp_orchestration\OrchestrationProviderPluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class OrchestrationProvider extends Plugin {

  /**
   * The plugin id.
   *
   * @var string
   */
  public $id;

  /**
   * Plugin name.
   *
   * @var string
   */
  public $name;

  /**
   * Description of the orchestration provider type.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * Name of schema.
   *
   * @var string
   */
  public $schema;

  /**
   * The id of the related configuration entity.
   *
   * @var string
   */
  public $config_entity_id;

}
