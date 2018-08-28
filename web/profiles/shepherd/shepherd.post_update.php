<?php

/**
 * @file
 * Post update hooks for the Shepherd profile.
 */

/**
 * Enable drush_cmi_tools.
 */
function shepherd_post_update_enable_drush_cmi_tools() {
  \Drupal::service('module_installer')->install(['drush_cmi_tools']);
}
