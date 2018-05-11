<?php

namespace Drupal\shp_custom\Entity\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\Access\GroupContentAccessControlHandler;
use Drupal\group\Entity\GroupContent;
use Drupal\group\Entity\GroupContentType;
use Drupal\group\Entity\GroupInterface;

/**
 * Access controller for the Group entity.
 *
 * @see \Drupal\group\Entity\Group.
 */
class ShepherdGroupContentAccessControlHandler extends GroupContentAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $group_content_type = GroupContentType::load($entity_bundle);
    $group_access_result = $group_content_type->getContentPlugin()->createAccess($context['group'], $account);

    if ($context['group']->bundle() !== 'shp_site') {
      // Exit early when we just need to check the group for access.
      return $group_access_result;
    }

    // Deal with sites inheriting project group permissions.
    $project_access_result = $this->projectCreateAccess($account, $context['group']);

    // Combine the access results.
    if ($group_access_result->isNeutral() && $project_access_result->isNeutral()) {
      return AccessResult::neutral();
    }
    return $group_access_result->isAllowed() || $project_access_result->isAllowed() ? AccessResult::allowed() : AccessResult::forbidden();
  }

  /**
   * Check access according to the project group permissions.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User account proxy.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The site group.
   *
   * @return bool|\Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function projectCreateAccess(AccountInterface $account, GroupInterface $group) {
    $group_content = $group->getContent();
    $site = reset($group_content)->getEntity();
    $groups = GroupContent::loadByEntity($site);
    $project_groups = array_filter($groups, function ($group) {
      return $group->bundle() === 'shp_project-group_node-shp_site';
    });
    $project_group = reset($project_groups)->getGroup();
    $project_group_content_type_id = $project_group->getGroupType()->getContentPlugin('group_membership')->getContentTypeConfigId();
    $project_group_content_type = GroupContentType::load($project_group_content_type_id);
    return $project_group_content_type->getContentPlugin()->createAccess($project_group, $account);
  }

}
