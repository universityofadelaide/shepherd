<?php

/**
 * @file
 * Provide views data for shp_custom.module.
 */

/**
 * Implements hook_views_data_alter().
 */
function shp_custom_views_data_alter(array &$data) {

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
      'title' => t('Environment Status'),
      'help' => t('Show environment status in terms of active, failed etc.'),
      'id' => 'site_environment_status',
    ],
  ];

}
