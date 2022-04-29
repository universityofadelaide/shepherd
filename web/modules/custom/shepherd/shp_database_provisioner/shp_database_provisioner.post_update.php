<?php

/**
 * @file
 * Post update hooks for the Shepherd database provisioner module.
 */

/**
 * Implements hook_aggregator_fetcher_info_alter().
 */
function shp_database_provisioner_post_update_set_options() {
  $config = \Drupal::configFactory()->getEditable('shp_database_provisioner.settings');
  if ($config->get('options') === NULL) {
    $config->set('options', '');
    $config->save();
  }
}
