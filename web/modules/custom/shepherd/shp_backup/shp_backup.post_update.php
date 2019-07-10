<?php

/**
 * @file
 * Post update hooks for shp_backup.
 */

/**
 * Remove old config keys.
 */
function shp_backup_post_update_remove_config() {
  \Drupal::configFactory()->getEditable('shp_backup.settings')
    ->clear('root_dir')
    ->clear('backup_command')
    ->clear('restore_command')
    ->save();
}
