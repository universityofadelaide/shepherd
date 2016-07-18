<?php

namespace Drupal\ua_sm_custom\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\node\Entity\Node;

/**
 * Field handler to show environments for a site.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("site_environments")
 */
class SiteEnvironments extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {}

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $site_id = $values->_entity->nid->value;

    // Get machine names of all environments for site.
    $environment_nids = \Drupal::entityQuery('node')
      ->condition('type', 'ua_sm_environment')
      ->condition('field_ua_sm_site', $site_id)
      ->execute();
    $environments = Node::loadMultiple($environment_nids);
    $environment_machine_names = array_map(function ($environment) {
      return $environment->field_ua_sm_machine_name->value;
    }, $environments);

    return [
      '#type' => 'markup',
      '#markup' => implode(', ', $environment_machine_names),
    ];
  }

}
