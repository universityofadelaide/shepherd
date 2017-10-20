<?php

namespace Drupal\shp_custom\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to add the status of the environment as a whole.
 *
 * This works by querying site instance(s) state.
 *
 * @package Drupal\shp_custom\Plugin\views\field
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("site_environment_terminal")
 */
class SiteEnvironmentTerminal extends FieldPluginBase {

  /**
   * Stores the result of the query to reuse later.
   *
   * @var array
   */
  protected $build;

  /**
   * {@inheritdoc}
   */
  public function query() {}

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $orchestrationProvider = \Drupal::service('plugin.manager.orchestration_provider')
      ->getProviderInstance();
    $entity = $values->_entity;
    $site = $entity->field_shp_site->first()->entity;
    $project = $site->field_shp_project->first()->entity;

    $terminal = $orchestrationProvider->getTerminalUrl(
      $project->getTitle(),
      $site->field_shp_short_name->value,
      $entity->id()
    );

    $build['environment_status'] = [
      '#markup' => $terminal,
    ];
    return $build;
  }

}
