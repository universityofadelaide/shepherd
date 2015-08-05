<?php

/**
 * @file
 * Contains Drupal\ua_sm_custom\Controller\UserSitesRolesController.
 */

namespace Drupal\ua_sm_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\Core\Form\OptGroup;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller to list sites and roles associated with a user.
 */
class UserSitesRolesController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function index($account_id) {

    // Basic account id validation.
    if (!ctype_alnum($account_id)) {
      throw new NotFoundHttpException();
    }

    // Load all roles associated with this user.
    $user_role_ids = \Drupal::entityQuery('paragraph')
      ->condition('type', 'ua_sm_user_role')
      ->condition('field_ua_sm_user_id', $account_id)
      ->execute();

    if (count($user_role_ids) === 0) {
      return ['#markup' => t("The user doesn't have access to any sites yet.")];
    }

    $entity_manager = \Drupal::entityManager();
    $user_roles = $entity_manager->getStorage('paragraph')->loadMultiple($user_role_ids);

    // Load all sites associated with this user.
    $site_ids = \Drupal::entityQuery('node')
      ->condition('type', 'ua_sm_site')
      ->condition('field_ua_sm_users', $user_role_ids, 'IN')
      ->execute();

    $sites = Node::loadMultiple($site_ids);

    // Err, ma gerd. Load the field options to get human readable roles.
    // @TODO: Make this not err ma gerd.
    $user_role = reset($user_roles);
    $definitions = \Drupal::entityManager()->getFieldStorageDefinitions('paragraph', 'ua_sm_site');
    $definition = $definitions['field_ua_sm_role'];
    $entity_type = \Drupal::entityManager()->loadEntityByUuid('paragraph', $user_role->uuid());
    $provider = $definition->getOptionsProvider('value', $entity_type);
    $options = OptGroup::flattenOptions($provider->getPossibleOptions());

    $header = [
      t('Site'),
      t('Role'),
    ];

    $rows = [];
    foreach ($sites as $site) {
      foreach ($site->field_ua_sm_users as $user_role) {
        if ($account_id == $user_roles[$user_role->target_id]->field_ua_sm_user_id->value) {
          $rows[] = [
            $site->title->value,
            $options[$user_roles[$user_role->target_id]->field_ua_sm_role->value],
          ];
        }
      }
    }

    $output = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => [
        'class' => ['sites-roles-table'],
      ],
    ];

    return $output;
  }

  /**
   * Callback to get the page title for the user permissions page.
   *
   * @param string account_id
   *   An A number E.g. a12345678.
   *
   * @return string
   *   Translated and escaped page title.
   */
  public function getPageTitle($account_id) {
    return t('Permissions for @account', ['@account' => $account_id]);
  }

}
