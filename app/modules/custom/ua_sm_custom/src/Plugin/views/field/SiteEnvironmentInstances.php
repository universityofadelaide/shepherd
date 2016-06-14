<?php
/**
 * @file
 * Definition of Drupal\ua_sm_custom\Plugin\views\field\SiteEnvironmentInstances.
 */

namespace Drupal\ua_sm_custom\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\Core\Url;
use Drupal\views\ResultRow;
use Drupal\node\Entity\Node;

/**
 * Field handler to add site instance links to environments.
 *
 * @package Drupal\ua_sm_custom\Plugin\views\field
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("site_environment_instances")
 */
class SiteEnvironmentInstances extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $values->_entity;
    $environment = $entity->id();
    $instance_ids = \Drupal::entityQuery('node')
      ->condition('type', 'ua_sm_site_instance')
      ->condition('field_ua_sm_environment', $environment)
      ->execute();
    $instances = Node::loadMultiple($instance_ids);
    $urls = [
      '#theme' => 'item_list',
      '#empty' => [
        '#markup' => $this->t('There are no instances running'),
      ],
      '#list_type' => 'ul',
    ];
    foreach ($instances as $instance) {
      $server = Node::load($instance->field_ua_sm_server->target_id);
      $hostname = $server->field_ua_sm_hostname->value;
      $port = $instance->field_ua_sm_http_port->value;
      $urls['#items']["$hostname$port"] = [
        '#type' => 'link',
        '#title' => $this->t("$hostname:$port"),
        '#url' => Url::fromUri("http://${hostname}:${port}"),
      ];
    }
    $build['environment_instances'] = $urls;

    return $build;
  }

}
