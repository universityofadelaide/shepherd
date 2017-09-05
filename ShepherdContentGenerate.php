<?php

/**
 * @file
 * Contains programmatic creation of Shepherd nodes for use by `drush scr`.
 */

use Drupal\node\Entity\Node;
use Drupal\shp_orchestration\Entity\OpenShiftConfigEntity;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;

$domain_name = getenv("OPENSHIFT_DOMAIN") ?: '192.168.99.100.nip.io';
$openshift_url = getenv("OPENSHIFT_URL") ?: 'https://192.168.99.100:8443';
$token = trim(getenv("TOKEN"));
$database_host = getenv("DB_HOST") ?: 'mysql-myproject.' . $domain_name;
$database_port = getenv("DB_PORT") ?: '31632';

// Check that the auth TOKEN environment variable is available.
if (empty($token)) {
  echo "To generate default configuration for development, export your auth TOKEN from your host.\n";
  echo "export TOKEN=some-token\n";
  echo "You can then safely re-run `robo dev:content-generate`\n";
  exit(1);
}

// Set deployment database config.
$db_provisioner_config = \Drupal::service('config.factory')->getEditable('shp_database_provisioner.settings');
$db_provisioner_config->set(
  'host',
  $database_host
);
$db_provisioner_config->set(
  'port',
  $database_port
);
$db_provisioner_config->save();

$openshift_config = [
  'endpoint'   => $openshift_url,
  'token'      => $token,
  'namespace'  => 'myproject',
  'verify_tls' => FALSE,
  'id'         => 'openshift',
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

if (!$development = taxonomy_term_load_multiple_by_name('Development', 'shp_environment_types')) {
  $development_env = Term::create([
    'vid'                   => 'shp_environment_types',
    'name'                  => 'Development',
    'field_shp_base_domain' => $domain_name,
  ]);
  $development_env->save();

  $production_env = Term::create([
    'vid'  => 'shp_environment_types',
    'name' => 'Production',
    'field_shp_base_domain' => $domain_name,
  ]);
  $production_env->save();
}
else {
  $development_env = reset($development);
  echo "Taxonomy already setup.\n";
}

$distribution = Node::load(1);
if (!$distribution) {
  $distribution = Node::create([
    'type'                     => 'shp_distribution',
    'langcode'                 => 'en',
    'uid'                      => '1',
    'status'                   => 1,
    'title'                    => 'WCMS D8',
    'field_shp_git_repository' => [['value' => 'git@gitlab.adelaide.edu.au:web-team/ua-wcms-d8.git']],
    'field_shp_builder_image'  => [['value' => 'uofa/s2i-shepherd-drupal']],
    'field_shp_build_secret'   => [['value' => 'build-key']],
    'field_shp_env_vars'       => [['key' => 'SHEPHERD_INSTALL_PROFILE', 'value' => 'ua']],
  ]);
  $distribution->save();
}
else {
  echo "Distribution already setup.\n";
}

$site = Node::load(2);
if (!$site) {
  $site = Node::create([
    'type'                   => 'shp_site',
    'langcode'               => 'en',
    'uid'                    => '1',
    'status'                 => 1,
    'title'                  => 'Test Site',
    'field_shp_namespace'    => 'myproject',
    'field_shp_short_name'   => 'test',
    'field_shp_domain'       => 'test-site.' . $domain_name,
    'field_shp_path'         => '/test-path',
    'field_shp_distribution' => [['target_id' => $distribution->id()]],
  ]);
  $site->moderation_state->value = 'published';
  $site->save();
}
else {
  echo "Site already setup.\n";
}

$env = Node::load(3);
if (!$env) {
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
  $env->moderation_state->value = 'published';
  $env->save();
}
else {
  echo "Environment already setup.\n";
}

$oc_user_result = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['name' => 'oc']);
/** @var \Drupal\user\Entity\User $oc_user */
$oc_user = $oc_user_result ? reset($oc_user_result) : FALSE;
if (!$oc_user) {
  $oc_user = User::create([
    'name' => 'oc',
    'pass' => 'password',
    'status' => 1,
  ]);
  $oc_user->save();
}

/** @var \Drupal\group\Entity\GroupInterface $dist_group */
$dist_group = \Drupal::service('shp_content_types.group_manager')->load($distribution);
$dist_group->addMember($oc_user, ['group_roles' => ['shp_distribution-online-consulta']]);
