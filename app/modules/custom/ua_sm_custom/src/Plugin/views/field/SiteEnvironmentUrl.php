<?php

namespace Drupal\ua_sm_custom\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\Core\Url;
use Drupal\views\ResultRow;
use Drupal\node\Entity\Node;

/**
 * Field handler to add actual environment urls to environments view.
 *
 * @package Drupal\ua_sm_custom\Plugin\views\field
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("site_environment_url")
 */
class SiteEnvironmentUrl extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $environment = $values->_entity;
    $domain = $environment->field_ua_sm_domain_name->value;
    $path = $environment->field_ua_sm_site->entity->field_ua_sm_path->value;

    $build = [
      '#type' => 'link',
      '#title' => $domain . $path,
      '#url' => Url::fromUri('//' . $domain . $path),
    ];

    return $build;
  }

}
