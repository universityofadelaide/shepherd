<?php
/**
 * @file
 * Contains Drupal\ua_sm_custom\Service\Site.
 */

namespace Drupal\ua_sm_custom\Service;

use Drupal\Core\Entity\Entity;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * Class Site
 * @package Drupal\ua_sm_custom\Service
 */
class Site {

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
    $keyedArray = function($nodes) {
      $keyed_array = [];
      foreach ($nodes as $node) {
        $keyed_array[$node->id()] = $node;
      }
      return $keyed_array;
    };

    $nodes = [
      'ua_sm_site' => [$site->id() => $site],
      'ua_sm_environment' => $this->loadRelatedEntitiesByField($site, 'field_ua_sm_site', 'ua_sm_environment'),
      'ua_sm_distribution' => $keyedArray($site->field_ua_sm_distribution->referencedEntities()),
      'ua_sm_site_instance' => [],
      'ua_sm_platform' => [],
      'ua_sm_server' => [],
    ];

    foreach ($nodes['ua_sm_environment'] as $environment) {
      // Site instances.
      $nodes['ua_sm_site_instance'] += $this->loadRelatedEntitiesByField($environment, 'field_ua_sm_environment', 'ua_sm_site_instance');

      // Platforms.
      $nodes['ua_sm_platform'] += $this->loadRelatedEntitiesByField($environment, 'field_ua_sm_platform', 'ua_sm_platform');
    }

    // Servers.
    foreach ($nodes['ua_sm_site_instance'] as $site_instance) {
      $nodes['ua_sm_server'] += $keyedArray($site_instance->field_ua_sm_server->referencedEntities());
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

  /**
   * Loads all public keys for a site from users who have admin access.
   *
   * @param \Drupal\node\Entity\Node $site
   *   The site node.
   * @param string|bool $role
   *   Optional parameter to filter returned users by a role.
   *
   * @return array $users
   *   An array of users.
   */
  public function loadUsers(Node $site, $role = FALSE) {
    $user_paragraphs = $site->field_ua_sm_users->referencedEntities();
    $user_names = [];
    foreach ($user_paragraphs as $user_paragraph) {
      if (!$role || $user_paragraph->field_ua_sm_role->value == $role) {
        $user_names[] = $user_paragraph->field_ua_sm_user_id->value;
      }
    }
    $user_ids = \Drupal::entityQuery('user')
      ->condition('name', $user_names, 'in')
      ->execute();
    $users = User::loadMultiple($user_ids);
    return $users;
  }

}
