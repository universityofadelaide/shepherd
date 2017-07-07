<?php

/**
 * @file
 * Contains programmatic creation of Shepherd nodes for use by `drush scr`.
 */

use Drupal\node\Entity\Node;
use Drupal\shp_orchestration\Entity\OpenShiftConfigEntity;
use Drupal\taxonomy\Entity\Term;

$domain_name = getenv("OPENSHIFT_DOMAIN") ?: '192.168.99.100.nip.io';
$openshift_url = getenv("OPENSHIFT_URL") ?: 'https://192.168.99.100:8443';
$token = trim(getenv("TOKEN"));
$database_port = getenv("DB_PORT") ?: '31632';

// Check that the auth TOKEN environment variable is available.
if (empty($token)) {
  echo "To generate default configuration for development, export your auth TOKEN from your host.\n";
  echo "export TOKEN=some-token\n";
  echo "You can then safely re-run `robo dev:content-generate`\n";
  exit(1);
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

// Set deployment database config.
$db_provisioner_config = \Drupal::service('config.factory')->getEditable('shp_database_provisioner.settings');
$db_provisioner_config->set(
  'host',
  'mysql-myproject.' . $domain_name
);
$db_provisioner_config->set(
  'port',
  $database_port
);
$db_provisioner_config->save();

$openshift_config = [
  'endpoint'  => $openshift_url,
  'token'     => $token,
  'namespace' => 'myproject',
  'mode'      => 'dev',
  'id'        => 'openshift',
];

// Configure OpenShift as orchestration provider.
if ($openshift = OpenShiftConfigEntity::load('openshift')) {
  // If config already exists, replace with current values.
  foreach ($openshift_config as $key => $value) {
    $openshift->set($key, $value);
  }
}
else {
  $openshift = OpenShiftConfigEntity::create($openshift_config);
}
$openshift->save();

$development_env = Term::create([
  'vid'                   => 'shp_environment_types',
  'name'                  => 'Development',
  'field_shp_base_domain' => $domain_name,
]);
$development_env->save();

$distribution = Node::create([
  'type'                     => 'shp_distribution',
  'langcode'                 => 'en',
  'uid'                      => '1',
  'status'                   => 1,
  'title'                    => 'WCMS D8',
  'field_shp_git_repository' => [['value' => 'git@gitlab.adelaide.edu.au:web-team/ua-wcms-d8.git']],
  'field_shp_builder_image'  => [['value' => 'uofa/s2i-shepherd-drupal']],
  'field_shp_build_secret'   => [['value' => 'build-key']],
]);
$distribution->save();

$site = Node::create([
  'type'                    => 'shp_site',
  'langcode'                => 'en',
  'uid'                     => '1',
  'status'                  => 1,
  'title'                   => 'Test Site',
  'field_shp_namespace'     => 'myproject',
  'field_shp_short_name'    => 'test',
  'field_shp_domain'        => $domain_name,
  'field_shp_path'          => '/',
  'field_shp_distribution'  => [['target_id' => $distribution->id()]],
]);
$site->save();

$env = Node::create([
  'type'                       => 'shp_environment',
  'langcode'                   => 'en',
  'uid'                        => '1',
  'status'                     => 1,
  'field_shp_domain'           => $site->field_shp_domain->value,
  'field_shp_path'             => $site->field_shp_path->value,
  'field_shp_environment_type' => [['target_id' => $development_env->id()]],
  'field_shp_git_reference'    => 'shepherd',
  'field_shp_site'             => [['target_id' => $site->id()]],
]);
$env->save();
