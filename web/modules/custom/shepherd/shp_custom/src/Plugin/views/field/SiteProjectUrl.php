<?php

namespace Drupal\shp_custom\Plugin\views\field;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\shp_orchestration\TokenNamespaceTrait;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to add project urls to sites view.
 *
 * @package Drupal\shp_custom\Plugin\views\field
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("site_project_url")
 */
class SiteProjectUrl extends FieldPluginBase {

  use TokenNamespaceTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {}

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $account = \Drupal::currentUser();

    if (!$account->hasPermission('access administration pages')) {
      return [];
    }

    $entity = $values->_entity;

    $this->config = \Drupal::configFactory()->get('shp_orchestration.settings');
    $endpoint = $this->config->get('connection.endpoint');

    // This search/replace is for development.
    $endpoint = str_replace('api.crc.', 'console-openshift-console.apps-crc.', $endpoint);

    // This one is for production.
    $endpoint = str_replace('api.', 'console-openshift-console.apps.', $endpoint);

    // The port isn't used through the UI.
    $endpoint = str_replace(':6443', '', $endpoint);

    $namespace = $this->buildProjectName($entity->id());

    return [
      '#type' => 'markup',
      '#markup' => Link::fromTextAndUrl($namespace, Url::fromUri($endpoint . '/k8s/cluster/projects/' . $namespace, [
        'attributes' => [
          'target' => '_blank',
        ],
      ]))->toString(),
    ];
  }

}
