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
      'id' => 'site_user_role_operations',
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
      'id' => 'site_environments',
    ],
  ];

  $data['node_field_data']['site_environments_operations'] = [
    'title' => t('Site Environment Operations'),
    'field' => [
      'title' => t('Operations'),
      'help' => t('Show operations that can be performed on an environment.'),
      'id' => 'site_environments_operations',
    ],
  ];

  $data['node_field_data']['site_environment_instances'] = [
    'title' => t('Site Environment Instances'),
    'field' => [
      'title' => t('Instances'),
      'help' => t('Show links to instances of an environment.'),
      'id' => 'site_environment_instances',
    ],
  ];

  $data['node_field_data']['site_environment_url'] = [
    'title' => t('Site Environment Url'),
    'field' => [
      'title' => t('Url'),
      'help' => t('Show clickable url for an environment.'),
      'id' => 'site_environment_url',
    ],
  ];

  $data['node_field_data']['site_environment_status'] = [
    'title' => t('Site Environment Status'),
    'field' => [
      'title' => t('Status'),
      'help' => t('Show environment status in terms of instances active, failed etc.'),
      'id' => 'site_environment_status',
    ],
  ];

}
