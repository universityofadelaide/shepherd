<?php

/**
 * @file
 * Provide views data for shp_backup.module.
 */

/**
 * Implements hook_views_data_alter().
 */
function shp_backup_views_data_alter(array &$data) {

  $data['node_field_data']['shp_backup_operations'] = [
    'title' => t('Site Environment Operations'),
    'field' => [
      'title' => t('Operations'),
      'help' => t('Show operations that can be performed on an environment.'),
      'id' => 'shp_backup_operations',
    ],
  ];

}
