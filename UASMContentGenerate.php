<?php
/**
 * @file
 * Contains programmatic creation of UASM nodes for use by `drush scr`.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;

/**
 * Override production default values for local dev.
 */
$environment_defaults = [
  'field_ua_sm_database_host' => '{docker_host_ip}',
  'field_ua_sm_git_reference' => 'develop',
];
foreach ($environment_defaults as $field_name => $field_value) {
  $field_config = FieldConfig::loadByName('node', 'ua_sm_environment', $field_name);
  $field_config->setDefaultValue($field_value);
  $field_config->save();
}

/**
 * Create a build server.
 */
$dev_server = Node::create([
  'type' => 'ua_sm_server',
  'langcode' => 'en',
  'uid' => '1',
  'status' => 1,
  'title' => 'Dev server',
  'field_ua_sm_hostname' => [['value' => 'docker-host']],
  'field_ua_sm_ssh_user' => [['value' => 'root']],
]);
$dev_server->save();

/**
 * Create a database server.
 */
$db_server = Node::create([
  'type' => 'ua_sm_server',
  'langcode' => 'en',
  'uid' => '1',
  'status' => 1,
  'title' => 'DB server',
  'field_ua_sm_hostname' => [['value' => 'mysql']],
  'field_ua_sm_ssh_user' => [['value' => 'root']],
]);
$db_server->save();

/**
 * Create first docker host server.
 */
$docker_host_server_1 = Node::create([
  'type' => 'ua_sm_server',
  'langcode' => 'en',
  'uid' => '1',
  'status' => 1,
  'title' => 'Docker Host 1',
  'field_ua_sm_hostname' => [['value' => 'docker-host']],
  'field_ua_sm_port_range_start' => [['value' => 10000]],
  'field_ua_sm_port_range_end' => [['value' => 12000]],
  'field_ua_sm_ssh_user' => [['value' => 'root']],
]);
$docker_host_server_1->save();

/**
 * Create second docker host server.
 */
$docker_host_server_2 = Node::create([
  'type' => 'ua_sm_server',
  'langcode' => 'en',
  'uid' => '1',
  'status' => 1,
  'title' => 'Docker Host 2',
  'field_ua_sm_hostname' => [['value' => 'docker-host']],
  'field_ua_sm_port_range_start' => [['value' => 12001]],
  'field_ua_sm_port_range_end' => [['value' => 14000]],
  'field_ua_sm_ssh_user' => [['value' => 'root']],
]);
$docker_host_server_2->save();

/**
 * Create a platform.
 */
$platform = Node::create([
  'type' => 'ua_sm_platform',
  'langcode' => 'en',
  'uid' => '1',
  'status' => 1,
  'title' => 'Dev Platform',
  'field_ua_sm_build_server' => [['target_id' => $dev_server->id()]],
  'field_ua_sm_web_servers' => [
    ['target_id' => $docker_host_server_1->id()],
    ['target_id' => $docker_host_server_2->id()]
  ],
  'field_ua_sm_database_servers' => [['target_id' => $db_server->id()]],
  'field_ua_sm_task_runner' => [['value' => 'jenkins']],
  'field_ua_sm_docker_registry' => [['value' => 'registry:5000']],
]);
$platform->save();

/**
 * Create a WCMS "dev" distribution.
 */
$distribution = Node::create([
  'type' => 'ua_sm_distribution',
  'langcode' => 'en',
  'uid' => '1',
  'status' => 1,
  'title' => 'WCMS D8 Dev Distribution',
  'field_ua_sm_git_repository' => [['value' => 'git@gitlab.adelaide.edu.au:web-team/ua-wcms-d8.git']],
  'field_ua_sm_box_type' =>       [['value' => 'web']],
]);
$distribution->save();

/**
 * Create a WCMS "dev" site.
 *
 * This spawns a "UAT" environment and a corresponding site instance.
 */
$site = Node::create([
  'type' => 'ua_sm_site',
  'langcode' => 'en',
  'uid' => '1',
  'status' => 1,
  'title' => 'wcms site',
  'field_ua_sm_site_title' =>       [['value' => 'WCMS Site']],
  'field_ua_sm_top_menu_style' =>   [['value' => 'mega_menu']],
  'field_ua_sm_authoriser_name' =>  [['value' => 'Prancy']],
  'field_ua_sm_authoriser_email' => [['value' => 'prancy@adelaide.edu.au']],
  'field_ua_sm_maintainer_name' =>  [['value' => 'Banana']],
  'field_ua_sm_maintainer_email' => [['value' => 'banana@adelaide.edu.au']],
  'field_ua_sm_domain_name' =>      [['value' => 'adelaide.dev']],
  'field_ua_sm_distribution' =>     [['target_id' => $distribution->id()]],
  'field_ua_sm_admin_email' =>      [['value' => 'admin@localhost']],
  'field_ua_sm_admin_password' =>   [['value' => 'password']],
  'field_ua_sm_admin_user' =>       [['value' => 'admin']],
]);
$site->save();
