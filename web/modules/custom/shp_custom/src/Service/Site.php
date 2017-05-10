<?php
/**
 * @file
 * Contains Drupal\shp_custom\Service\Site.
 */

namespace Drupal\shp_custom\Service;

use Drupal\Core\Entity\Entity;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\user\Entity\User;

/**
 * Class Site
 * @package Drupal\shp_custom\Service
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
      'shp_site' => [$site->id() => $site],
      'shp_environment' => $this->loadRelatedEntitiesByField($site, 'field_shp_site', 'shp_environment'),
      'shp_distribution' => $keyedArray($site->field_shp_distribution->referencedEntities()),
      'shp_site_instance' => [],
      'shp_platform' => [],
      'shp_server' => [],
    ];

    foreach ($nodes['shp_environment'] as $environment) {
      // Platforms.
      $nodes['shp_platform'] += $this->loadRelatedEntitiesByField($environment, 'field_shp_platform', 'shp_platform');
    }

    // Servers.
    foreach ($nodes['shp_site_instance'] as $site_instance) {
      $nodes['shp_server'] += $keyedArray($site_instance->field_shp_server->referencedEntities());
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
    $user_paragraphs = $site->field_shp_users->referencedEntities();
    $user_names = [];
    foreach ($user_paragraphs as $user_paragraph) {
      if (!$role || $user_paragraph->field_shp_role->value == $role) {
        $user_names[] = $user_paragraph->field_shp_user_id->value;
      }
    }
    $user_ids = \Drupal::entityQuery('user')
      ->condition('name', $user_names, 'in')
      ->execute();
    $users = User::loadMultiple($user_ids);
    return $users;
  }

  /**
   * Check if user role combo exists on site.
   *
   * @param $site the site to check for the combo.
   * @param $account the user to check for.
   * @param $role the role to check for.
   * @return bool $user_role_exists whether the user role combo exists.
   */
  public function userRoleExists($site, $account, $role) {
    $user_roles = $site->get('field_shp_users');
    $user_role_exists = FALSE;
    foreach ($user_roles as $user_role_field) {
      $id = $user_role_field->getValue()['target_id'];
      $user_role = Paragraph::load($id);
      if ($user_role->field_shp_user->target_id == $account->id() && $user_role->field_shp_role->value == $role) {
        $user_role_exists = TRUE;
      }
    }
    return $user_role_exists;
  }

}
