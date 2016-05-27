<?php
/**
 * @file
 * Definition of Drupal\ua_sm_custom\Plugin\views\field\SiteUserRoleOperations.
 */

namespace Drupal\ua_sm_custom\Plugin\views\field;

use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to perform operations on users assigned to a site.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("site_user_role_operations")
 */
class SiteUserRoleOperations extends FieldPluginBase {

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
    $node_id = $entity->nid->value;
    $uid = $values->users_field_data_paragraph__field_ua_sm_user_uid;
    $build = [];
    if ($uid) {
      $edit_url = Url::fromRoute('ua_sm_custom.user-edit-form', ['node' => $node_id, 'user' => $uid]);
      $delete_url = Url::fromRoute('ua_sm_custom.user-delete-form', ['node' => $node_id, 'user' => $uid]);

      $build['edit_user_role'] = [
        '#type' => 'link',
        '#title' => t('Edit'),
        '#url' => $edit_url,
        '#options' => [
          'attributes' => [
            'class' => [
              'button',
              'c-btn',
            ],
          ],
        ],
      ];

      $build['delete_user_role'] = [
        '#type' => 'link',
        '#title' => t('Delete'),
        '#url' => $delete_url,
        '#options' => [
          'attributes' => [
            'class' => [
              'button',
              'c-btn',
            ],
          ],
        ],
      ];
    }

    return $build;
  }

}
