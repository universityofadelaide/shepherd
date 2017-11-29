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
 * @ViewsField("site_environment_status")
 */
class SiteEnvironmentStatus extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {}

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    // How do we get the status.
    // Use the orchestration provider to get the plugin.
    // @todo - inject the service.
    $environments_status = \Drupal::service('shp_orchestration.status')->get($values->_entity);

    // Is the environment running ?
    if ($environments_status['running']) {
      // If pods are available its running else its building.
      $status = ($environments_status['available_pods'] > 0) ? 'Running' : 'Building';
    }
    else {
      // If pods attempting to run but status is false, the state is broken.
      $status = ($environments_status['available_pods'] === 0) ? 'Stopped' : 'Failed';
    }

    $build['environment_status'] = [
      '#plain_text' => $status,
      // @todo - figure out cache tags for views field plugins.
      '#cache' => [
        'disabled' => TRUE,
      ],
    ];
    return $build;
  }

}
