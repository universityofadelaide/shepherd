<?php

namespace Drupal\shp_roles\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class SiteUserRoles.
 */
class SiteUserRoles implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')
    );
  }

  /**
   * Fetches all the roles for a user relating to a site.
   *
   * @param \Drupal\user\UserInterface $username
   *   The user being enquired about.
   * @param \Drupal\node\NodeInterface $site
   *   The site being enquired about.
   *
   * @return string
   *   A list of roles.
   */
  public function json(UserInterface $username, NodeInterface $site) {
    // Find all the groups for this site.
    // @todo Replace this loading code with GroupManager::loadAll()?
    $group_manager = $this->entityTypeManager
      ->getStorage('group_content');
    $project_groups = $group_manager->loadByProperties([
      'entity_id' => $site->id(),
      'type' => 'shp_project-group_node-shp_site',
    ]);
    $site_groups = $group_manager->loadByProperties([
      'entity_id' => $site->id(),
      'type' => 'shp_site-group_node-shp_site',
    ]);
    $group_contents = array_merge($project_groups, $site_groups);

    /** @var \Drupal\group\Entity\GroupContentInterface $group_content */
    $group_ids = [];
    foreach ($group_contents as $group_content) {
      $group_ids[] = $group_content->getGroup()->id();
    }
    $groups = $this->entityTypeManager
      ->getStorage('group')
      ->loadMultiple($group_ids);

    // Find all the roles this user has for each group.
    $roles = [];
    foreach ($groups as $group) {
      if ($member = $group->getMember($username)) {
        $group_roles = $member->getRoles();
        $new_roles = array_map(function ($group_role) {
          return $group_role->getThirdPartySetting('shp_roles', 'synced_role');
        }, $group_roles);
        $roles = array_merge($roles, array_filter(array_values($new_roles)));
      }
    }

    // Collapse the roles.
    $roles = array_unique($roles);
    asort($roles);

    // Dream.
    return new JsonResponse($roles);
  }

}
