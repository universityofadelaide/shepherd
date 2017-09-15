<?php

namespace Drupal\shp_custom\Plugin\views\field;

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
  public function query() {
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    // Ideally this would be injected. However there are issues with provider
    // throwing exceptions during install. These services will get cached to be
    // ready to call during build. The orchestration provider needs config to
    // work correctly and we get ourselves in a trap where the provider is not
    // configured ( during install ) but this plugin is attempting to register
    // the service. So static it is.
    $orchestrationProvider = \Drupal::service('plugin.manager.orchestration_provider')->getProviderInstance();
    $entity = $values->_entity;
    $site = $entity->field_shp_site->first()->entity;
    $distribution = $site->field_shp_distribution->first()->entity;
    $url = $orchestrationProvider->getEnvironmentUrl(
      $distribution->getTitle(),
      $site->field_shp_short_name->value,
      $entity->id()
    );

    if ($url) {
      $field = [
        '#type' => 'link',
        '#title' => substr($url->toString(), 2),
        '#url' => $url,
      ];
    }
    else {
      $field = [
        '#type' => 'markup',
        '#markup' => 'No url set',
      ];
    }
    return $field;
  }

}
