<?php
/**
 * @file
 * Contains programmatic creation of UASM nodes for use by `drush scr`.
 */

use Drupal\node\Entity\Node;

/**
 * Create a "dev" server.
 *
 * NID: 1
 */
$build_server = Node::create([
  'type' => 'ua_sm_server',
  'langcode' => 'en',
  'uid' => '1',
  'status' => 1,
  'title' => 'dev',
  'field_ua_sm_hostname' => [['value' => 'dev']],
  'field_ua_sm_ssh_user' => [['value' => 'root']],
]);
$build_server->save();

/**
 * Create a "docker-host" server.
 *
 * NID: 2
 */
$docker_host_server = Node::create([
  'type' => 'ua_sm_server',
  'langcode' => 'en',
  'uid' => '1',
  'status' => 1,
  'title' => 'docker-host',
  'field_ua_sm_hostname' => [['value' => 'docker-host']],
  'field_ua_sm_ssh_user' => [['value' => 'docker']],
]);
$docker_host_server->save();

/**
 * Create a "dev" platform.
 *
 * NID: 3
 */
$platform = Node::create([
  'type' => 'ua_sm_platform',
  'langcode' => 'en',
  'uid' => '1',
  'status' => 1,
  'title' => 'dev platform',
  'field_ua_sm_build_server' =>     [['target_id' => $build_server->id()]],
  'field_ua_sm_web_servers' =>      [['target_id' => $build_server->id()]],
  'field_ua_sm_database_servers' => [['target_id' => $build_server->id()]],
  'field_ua_sm_task_runner' =>      [['value' => 'jenkins']],
  'field_ua_sm_docker_registry' =>  [['value' => 'registry-backend:5000']],
]);
$platform->save();

/**
 * Create a WCMS "dev" distribution.
 *
 * NID: 4
 */
$distribution = Node::create([
  'type' => 'ua_sm_distribution',
  'langcode' => 'en',
  'uid' => '1',
  'status' => 1,
  'title' => 'wcms d8 dev distribution',
  'field_ua_sm_git_repository' => [['value' => 'git@gitlab.adelaide.edu.au:web-team/ua-wcms-d8.git']],
  'field_ua_sm_box_type' =>       [['value' => 'web']],
]);
$distribution->save();

/**
 * Create a WCMS "dev" site.
 *
 * NID: 5
 */
$site = Node::create([
  'type' => 'ua_sm_site',
  'langcode' => 'en',
  'uid' => '1',
  'status' => 1,
  'title' => 'wcms site',
  'field_ua_sm_site_title' =>       [['value' => 'wcms site']],
  'field_ua_sm_top_menu_style' =>   [['value' => 'mega_menu']],
  'field_ua_sm_authoriser_name' =>  [['value' => 'Ben']],
  'field_ua_sm_authoriser_email' => [['value' => 'ben@ben.com']],
  'field_ua_sm_maintainer_name' =>  [['value' => 'Ben']],
  'field_ua_sm_maintainer_email' => [['value' => 'ben@ben.com']],
  'field_ua_sm_distribution' =>     [['target_id' => $distribution->id()]],
  'field_ua_sm_admin_email' =>      [['value' => 'admin@localhost']],
  'field_ua_sm_admin_password' =>   [['value' => 'password']],
  'field_ua_sm_admin_user' =>       [['value' => 'admin']],
]);
$site->save();

/**
 * Create a WCMS "dev" environment.
 *
 * NID: 6
 */
$environment = Node::create([
  'type' => 'ua_sm_environment',
  'langcode' => 'en',
  'uid' => '1',
  'status' => 1,
  'title' => 'wcms environment',
  'field_ua_sm_site_title' =>    [['value' => 'wcms environment']],
  'field_ua_sm_machine_name' =>  [['value' => 'dev']],
  'field_ua_sm_domain_name' =>   [['value' => 'adelaide.dev']],
  'field_ua_sm_git_reference' => [['value' => 'develop']],
  'field_ua_sm_database_host' => [['value' => 'mysql']],
  'field_ua_sm_platform' =>      [['target_id' => $platform->id()]],
  'field_ua_sm_site' =>          [['target_id' => $site->id()]],
]);
$environment->save();
