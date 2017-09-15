<?php

/**
 * Implements hook_views_data_alter().
 */
function shp_drush_aliases_views_data_alter(array &$data) {

  $data['node_field_data']['drush_aliases_download'] = [
    'title' => t('Drush Aliases Download'),
    'field' => [
      'title' => t('Drush Aliases'),
      'help' => t('Show download button for drush aliases'),
      'id' => 'drush_aliases_download',
    ],
  ];

}
