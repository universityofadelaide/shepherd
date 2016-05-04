<?php

/**
 * @file
 * Provide views data for ua_sm_custom.module.
 */

/**
 * Implements hook_views_data_alter().
 */
function ua_sm_custom_views_data_alter(array &$data) {

  $data['paragraph__field_ua_sm_role']['site_user_role_operations'] = [
    'title' => t('Site User Role Operations'),
    'field' => [
      'title' => t('Operations'),
      'help' => t('Perform operations on an assigned user to a site'),
      'id' => 'site_user_role_operations'
    ],
    'relationship' => [
      'group' => t('Paragraph'),
    ],
  ];

  $data['node_field_data']['site_environments'] = [
    'title' => t('Site Environments'),
    'field' => [
      'title' => t('Environments'),
      'help' => t('Show environments for a site.'),
      'id' => 'site_environments'
    ]
  ];

  $data['node_field_data']['site_environments_operations'] = [
    'title' => t('Site Environment Operations'),
    'field' => [
      'title' => t('Operations'),
      'help' => t('Show operations that can be performed on environments'),
      'id' => 'site_environments_operations'
    ]
  ];

}
