<?php

namespace Drupal\shp_content_types\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Plugin\GroupContentEnablerManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Class SiteGroupManager.
 *
 * @package Drupal\shp_content_types\Service
 */
class GroupManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The group content plugin manager.
   *
   * @var \Drupal\group\Plugin\GroupContentEnablerManagerInterface
   */
  protected $pluginManager;

  /**
   * Creates an instance of the SiteGroupManager service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\group\Plugin\GroupContentEnablerManagerInterface $pluginManager
   *   The group content plugin manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, GroupContentEnablerManagerInterface $pluginManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->pluginManager = $pluginManager;
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
   * Load the sync'd group associated with the given node.
   *
   * Each shp_site and shp_distribution node has a single group associated with
   * it that has a group type the same as the node. These special groups are a
   * convenience that allow administrators to assign users access to sites
   * or distributions (and their associated sites).
   *
   * @param \Drupal\node\NodeInterface $node
   *   Fetch the group associated with this node.
   *
   * @return \Drupal\group\Entity\GroupInterface
   *   The corresponding group.
   */
  public function load(NodeInterface $node) {
    /** @var \Drupal\group\Entity\GroupInterface $group */
    $group = NULL;

    // Fetch the group type with the same type as the $node.
    /** @var \Drupal\group\Entity\GroupTypeInterface $groupType */
    $groupType = $this->entityTypeManager->getStorage('group_type')->load($node->getType());
    // Fetch the content plugin with the same type as the $node.
    $plugin = $groupType->getContentPlugin('group_node:' . $node->getType());
    // Fetch the config id - may be a hash because they are quite long.
    $contentTypeConfigId = $plugin->getContentTypeConfigId();

    $group_contents = $this->entityTypeManager
      ->getStorage('group_content')
      ->loadByProperties([
        'type'      => $contentTypeConfigId,
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

  /**
   * Adds a site to the group associated with its distribution.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The site to be added to its associated distribution group.
   */
  public function addSiteToDistributionGroup(NodeInterface $node) {
    $distributions = $node->field_shp_project->referencedEntities();
    foreach ($distributions as $distribution) {
      $group = $this->load($distribution);
      $group->addContent($node, 'group_node:' . $node->getType());
    }
  }

}
