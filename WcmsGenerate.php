<?php

/**
 * @file
 * Contains programmatic creation of Shepherd nodes for use by `drush scr`.
 */

use Drupal\node\Entity\Node;

$domain_name = getenv("OPENSHIFT_DOMAIN") ?: '192.168.99.100.nip.io';
$openshift_url = getenv("OPENSHIFT_URL") ?: 'https://192.168.99.100:8443';

$database_host = getenv("DB_HOST") ?: 'mysql-myproject.' . $domain_name;
$database_port = getenv("DB_PORT") ?: '31632';

// Check that required variables are actually set.
$token = trim(getenv("TOKEN"));

$project = Node::create([
  'type'                     => 'shp_project',
  'langcode'                 => 'en',
  'uid'                      => '1',
  'status'                   => 1,
  'title'                    => 'UA WCMS',
  'field_shp_git_repository' => [['value' => 'git@gitlab.adelaide.edu.au:web-team/ua-wcms-d8.git']],
  'field_shp_builder_image'  => [['value' => 'uofa/s2i-shepherd-drupal']],
  'field_shp_build_secret'   => [['value' => 'build-key']],
  'field_shp_env_vars'       => [
    ['key' => 'SHEPHERD_INSTALL_PROFILE', 'value' => 'ua'],
    ['key' => 'REDIS_ENABLED', 'value' => '1'],
    ['key' => 'PUBLIC_DIR', 'value' => '/shared/public'],
    ['key' => 'PRIVATE_DIR', 'value' => '/shared/private'],
    ['key' => 'TMP_DIR', 'value' => '/shared/tmp'],
  ],
  'field_shp_readiness_probe_type' => [['value' => 'tcpSocket']],
  'field_shp_readiness_probe_port' => [['value' => '8080']],
  'field_shp_liveness_probe_type' => [['value' => 'tcpSocket']],
  'field_shp_liveness_probe_port' => [['value' => '8080']],
]);
$project->save();

$site = Node::create([
  'type'                      => 'shp_site',
  'langcode'                  => 'en',
  'uid'                       => '1',
  'status'                    => 1,
  'title'                     => 'WCMS Site',
  'field_shp_namespace'       => 'myproject',
  'field_shp_short_name'      => 'uawcms',
  'field_shp_domain'          => 'uawcms.' . $domain_name,
  'field_shp_git_default_ref' => 'shepherd',
  'field_shp_path'            => '/',
  'field_shp_project'         => [['target_id' => $project->id()]],
]);
$site->moderation_state->value = 'published';
$site->save();

$env = Node::create([
  'type'                       => 'shp_environment',
  'langcode'                   => 'en',
  'uid'                        => '1',
  'status'                     => 1,
  'field_shp_domain'           => 'uawcms-development.' . $domain_name,
  'field_shp_path'             => $site->field_shp_path->value,
  'field_shp_environment_type' => [['target_id' => 1]],
  'field_shp_git_reference'    => 'shepherd',
  'field_shp_site'             => [['target_id' => $site->id()]],
  'field_shp_update_on_image_change' => TRUE,
]);
$env->moderation_state->value = 'published';
$env->save();
