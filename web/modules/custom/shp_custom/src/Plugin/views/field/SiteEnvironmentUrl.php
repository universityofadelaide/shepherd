<?php

namespace Drupal\shp_custom\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\Core\Url;
use Drupal\views\ResultRow;
use Drupal\node\Entity\Node;

/**
 * Field handler to add actual environment urls to environments view.
 *
 * @package Drupal\shp_custom\Plugin\views\field
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
    $domain = $environment->field_shp_domain->value;
    $path = $environment->field_shp_site->entity->field_shp_path->value;

    // If the url is bunk, don't diaf.
    if (isset($domain)) {
      $url = Url::fromUri('//' . $domain . $path);
    }
    else {
      $url = '';
    }

    $build = [
      '#type' => 'link',
      '#title' => $domain . $path,
      '#url' => $url,
    ];

    return $build;
  }

}
