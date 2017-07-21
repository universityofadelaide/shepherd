<?php

namespace Drupal\shp_custom\Service;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * Class Site.
 *
 * @package Drupal\shp_custom\Service
 */
class Site {

  /**
   * Applies go live date if not set.
   *
   * @param \Drupal\node\Entity\Node $site
   *   The site node.
   * @param string $environment_type
   *   Environment type name.
   *
   * @return bool
   *   TRUE if applied go live date.
   */
  public function applyGoLiveDate(Node $site, string $environment_type) {
    if ($environment_type === "Production") {
      if (!isset($site->field_shp_go_live_date->value)) {
        $date = DrupalDateTime::createFromTimestamp(time());
        $date->setTimezone(new \DateTimeZone(DATETIME_STORAGE_TIMEZONE));
        $site->field_shp_go_live_date->setValue($date->format(DATETIME_DATETIME_STORAGE_FORMAT));
        $site->save();
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Load all entities related to a site.
   *
   * @param \Drupal\node\Entity\Node $site
   *   The site node.
   *
   * @return array
   *   An array of related entities keyed by type.
   */
  public function loadRelatedEntities(Node $site) {
    // referencedEntities() doesn't key by node id; This re-keys by node id.
    $keyedArray = function ($nodes) {
      $keyed_array = [];
      foreach ($nodes as $node) {
        $keyed_array[$node->id()] = $node;
      }
      return $keyed_array;
    };

    $nodes = [
      'shp_site' => [$site->id() => $site],
      'shp_environment' => $this->loadRelatedEntitiesByField($site, 'field_shp_site', 'shp_environment'),
      'shp_distribution' => $keyedArray($site->field_shp_distribution->referencedEntities()),
    ];

    foreach ($nodes['shp_environment'] as $environment) {
      // @todo Shepherd: Platforms are gone, what to do here?
    }

    return $nodes;
  }

  /**
   * Reverse loading of related entities for a specific field and node type.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The referenced node.
   * @param string $reference_field
   *   The entity reference field name.
   * @param string $node_type
   *   The node type.
   *
   * @return array
   *   An array of nodes
   */
  public function loadRelatedEntitiesByField(Node $node, $reference_field, $node_type) {
    $results = \Drupal::entityQuery('node')
      ->condition('type', $node_type)
      ->condition($reference_field, $node->id())
      ->condition('status', NODE_PUBLISHED)
      ->execute();
    return Node::loadMultiple($results);
  }

}
