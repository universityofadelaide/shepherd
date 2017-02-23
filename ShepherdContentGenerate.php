<?php
/**
 * @file
 * Contains programmatic creation of Shepherd nodes for use by `drush scr`.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;

$domain_name = getenv("DOMAIN");

/**
 * Override production default values for local dev.
 */
$environment_defaults = [
  'field_shp_database_host' => 'mysql',
  'field_shp_git_reference' => 'develop',
];
foreach ($environment_defaults as $field_name => $field_value) {
  $field_config = FieldConfig::loadByName('node', 'shp_environment', $field_name);
  $field_config->setDefaultValue($field_value);
  $field_config->save();
}

// Clobber env/domain config with dev versions.
\Drupal::service('config.factory')->getEditable('shp_custom.settings')->set(
  'environment_domains',
  [
    'prd' => $domain_name,
    'stg' => $domain_name,
    'uat' => $domain_name,
    'dev' => $domain_name,
  ]
)->save();

/**
 * Create a build server.
 */
$dev_server = Node::create([
  'type' => 'shp_server',
  'langcode' => 'en',
  'uid' => '1',
  'status' => 1,
  'title' => 'Dev server',
  'field_shp_hostname' => [['value' => 'docker-host']],
  'field_shp_ssh_user' => [['value' => 'root']],
]);
$dev_server->save();

/**
 * Create a database server.
 */
$db_server = Node::create([
  'type' => 'shp_server',
  'langcode' => 'en',
  'uid' => '1',
  'status' => 1,
  'title' => 'DB server',
  'field_shp_hostname' => [['value' => 'mysql']],
  'field_shp_ssh_user' => [['value' => 'root']],
]);
$db_server->save();

/**
 * Create first docker host server.
 */
$docker_host_server_1 = Node::create([
  'type' => 'shp_server',
  'langcode' => 'en',
  'uid' => '1',
  'status' => 1,
  'title' => 'Docker Host 1',
  'field_shp_hostname' => [['value' => 'docker-host']],
  'field_shp_port_range_start' => [['value' => 10000]],
  'field_shp_port_range_end' => [['value' => 12000]],
  'field_shp_ssh_user' => [['value' => 'root']],
]);
$docker_host_server_1->save();

/**
 * Create second docker host server.
 */
$docker_host_server_2 = Node::create([
  'type' => 'shp_server',
  'langcode' => 'en',
  'uid' => '1',
  'status' => 1,
  'title' => 'Docker Host 2',
  'field_shp_hostname' => [['value' => 'docker-host']],
  'field_shp_port_range_start' => [['value' => 12001]],
  'field_shp_port_range_end' => [['value' => 14000]],
  'field_shp_ssh_user' => [['value' => 'root']],
]);
$docker_host_server_2->save();

/**
 * Create a platform.
 */
$platform = Node::create([
  'type' => 'shp_platform',
  'langcode' => 'en',
  'uid' => '1',
  'status' => 1,
  'title' => 'Dev Platform',
  'field_shp_deploy_type' => [['value' => 'DOCKER_LOCAL']],
  'field_shp_build_server' => [['target_id' => $dev_server->id()]],
  'field_shp_web_servers' => [
    ['target_id' => $docker_host_server_1->id()],
    ['target_id' => $docker_host_server_2->id()]
  ],
  'field_shp_database_servers' => [['target_id' => $db_server->id()]],
  'field_shp_task_runner' => [['value' => 'jenkins']],
  'field_shp_docker_registry' => [['value' => "registry.$domain_name:5000"]],
]);
$platform->save();

/**
 * Create a WCMS "dev" distribution.
 */
$distribution = Node::create([
  'type' => 'shp_distribution',
  'langcode' => 'en',
  'uid' => '1',
  'status' => 1,
  'title' => 'WCMS D8 Dev Distribution',
  'field_shp_git_repository' => [['value' => 'git@gitlab.adelaide.edu.au:web-team/ua-wcms-d8.git']],
  'field_shp_box_type' =>       [['value' => 'uofa/apache2-php7-dev']],
]);
$distribution->save();

/**
 * Create a WCMS "dev" site.
 *
 * This spawns a "UAT" environment and a corresponding site instance.
 */
$site = Node::create([
  'type' =>                         'shp_site',
  'langcode' =>                     'en',
  'uid' =>                          '1',
  'status' =>                       1,
  'title' =>                        'wcms site',
  'field_shp_create_site' =>      TRUE,
  'field_shp_platform' =>         $platform->id(),
  'field_shp_git_reference' =>    $environment_defaults['field_shp_git_reference'],
  'field_shp_site_title' =>       [['value' => 'WCMS Site']],
  'field_shp_top_menu_style' =>   [['value' => 'mega_menu']],
  'field_shp_authoriser_name' =>  [['value' => 'Prancy']],
  'field_shp_authoriser_email' => [['value' => 'prancy@adelaide.edu.au']],
  'field_shp_maintainer_name' =>  [['value' => 'Banana']],
  'field_shp_maintainer_email' => [['value' => 'banana@adelaide.edu.au']],
  'field_shp_domain_name' =>      [['value' => "wcms-site.$domain_name"]],
  'field_shp_distribution' =>     [['target_id' => $distribution->id()]],
  'field_shp_admin_email' =>      [['value' => 'admin@localhost']],
  'field_shp_admin_password' =>   [['value' => 'password']],
  'field_shp_admin_user' =>       [['value' => 'admin']],
]);
$site->save();
