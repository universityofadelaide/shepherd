<?php

namespace Drupal\shp_content_types\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Class SiteGroupManager.
 *
 * @package Drupal\shp_content_types\Service
 */
class SiteGroupManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Creates an instance of the SiteGroupManager service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Creates a group for a given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Base the group on this node.
   *
   * @return \Drupal\group\Entity\GroupInterface
   *   The corresponding group.
   */
  public function create(NodeInterface $node) {
    /* @var $group \Drupal\group\Entity\GroupInterface */
    $group = $this->entityTypeManager
      ->getStorage('group')
      ->create([
        'type' => $node->getType(),
        'label' => $node->getTitle(),
      ]);
    $group->enforceIsNew();
    $group->save();

    // Associate the site with the group.
    $group->addContent($node, 'group_node:' . $node->getType());
    return $group;
  }

  /**
   * Deletes the sync'd group for a given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Delete the group associated with this node.
   */
  public function delete(NodeInterface $node) {
    if ($group = $this->load($node)) {
      $group->delete();
    }
  }

  /**
   * Load the site group associated with a site node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The site node.
   *
   * @return \Drupal\group\Entity\GroupInterface
   *   The corresponding site group.
   */
  public function load(NodeInterface $node) {
    $group = NULL;
    $group_contents = $this->entityTypeManager
      ->getStorage('group_content')
      ->loadByProperties([
        'type'      => 'shp_site-group_node-shp_site',
        'entity_id' => $node->id(),
      ]);

    /** @var \Drupal\group\Entity\GroupContentInterface $group_content */
    foreach ($group_contents as $group_content) {
      $group = $this->entityTypeManager
        ->getStorage('group')
        ->load($group_content->getGroup()->id());
    }

    return $group;
  }

}
