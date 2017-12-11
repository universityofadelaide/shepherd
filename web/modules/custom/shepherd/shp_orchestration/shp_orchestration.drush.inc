<?php

/**
 * @file
 * Drush integration for Shepherd Orchestration.
 */

/**
 * Implements hook_drush_command().
 */
function shp_orchestration_drush_command() {
  $commands = [];
  $commands['shepherd-process-job-queue'] = [
    'description' => 'Process any jobs in the job queue',
    'aliases' => ['shp-p'],
    'examples' => [
      'drush shepherd-process-job-queue' => 'Process any jobs in the job queue',
      'drush shp-p' => 'Process any jobs in the job queue',
    ],
  ];
  return $commands;
}

/**
 * Drush command logic for processing the job queue.
 */
function drush_shp_orchestration_shepherd_process_job_queue() {
  \Drupal::service('shp_orchestration.job_queue')->process();
  // @todo - would be a good idea to print what job id etc. was processed.
  drush_print(dt('Job queue processed'));
}
