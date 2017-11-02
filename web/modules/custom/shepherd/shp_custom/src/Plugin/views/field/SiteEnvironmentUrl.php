<?php

namespace Drupal\shp_custom\Plugin\views\field;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

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
  public function query() {}

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $values->_entity;
    $environment_term = Term::load($entity->field_shp_environment_type->target_id);

    // If its a protected environment, its production, show that url.
    if ($environment_term->field_shp_protect->value) {
      $site = $entity->field_shp_site->first()->entity;
      $domain_and_path = rtrim($site->field_shp_domain->value . $site->field_shp_path->value, '/');
      return Link::fromTextAndUrl(
        $domain_and_path,
        Url::fromUri('//' . $domain_and_path))->toRenderable();
    }
    else {
      $domain_and_path = rtrim($entity->field_shp_domain->value . $entity->field_shp_path->value, '/');
      return Link::fromTextAndUrl(
        $domain_and_path,
        Url::fromUri('//' . $domain_and_path))->toRenderable();
    }

  }

}
