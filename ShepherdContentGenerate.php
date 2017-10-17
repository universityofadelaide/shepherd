<?php

/**
 * @file
 * Contains programmatic creation of Shepherd nodes for use by `drush scr`.
 */

use Drupal\node\Entity\Node;
use Drupal\shp_orchestration\Entity\OpenShiftConfigEntity;
use Drupal\shp_redis_support\Entity\OpenShiftWithRedisConfigEntity;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;

$domain_name = getenv("OPENSHIFT_DOMAIN") ?: '192.168.99.100.nip.io';
$openshift_url = getenv("OPENSHIFT_URL") ?: 'https://192.168.99.100:8443';

$database_host = getenv("DB_HOST") ?: 'mysql-myproject.' . $domain_name;
$database_port = getenv("DB_PORT") ?: '31632';

// Check that required variables are actually set.
$token = trim(getenv("TOKEN"));
$example_repository = getenv("EXAMPLE_REPOSITORY");
if (empty($token) || empty($example_repository)) {
  echo "To generate default configuration for development, the TOKEN and EXAMPLE_REPOSITORY\n";
  echo "variables are required to be set. Export your auth TOKEN from your host and provide\n";
  echo "a repository to clone and build with.\n";
  echo "export TOKEN=some-token\n";
  echo "export EXAMPLE_REPOSITORY=some-repo-spec\n";
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

// Update settings to create a redis enabled version of the endpoint.
$openshift_config['id'] = 'openshift_with_redis';

// Configure OpenShift as orchestration provider.
if ($openshift = OpenShiftWithRedisConfigEntity::load('openshift_with_redis')) {
  // If config already exists, replace with current values.
  foreach ($openshift_config as $key => $value) {
    $openshift->set($key, $value);
  }
}
else {
  $openshift = OpenShiftWithRedisConfigEntity::create($openshift_config);
}
$openshift->save();

$orchestration_config = \Drupal::service('config.factory')->getEditable('shp_orchestration.settings');
$orchestration_config->set('selected_provider', 'openshift_with_redis');
$orchestration_config->save();

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
    'field_shp_protect' => TRUE,
    'field_shp_update_go_live' => TRUE,
  ]);
  $production_env->save();
}
else {
  $development_env = reset($development);
  echo "Taxonomy already setup.\n";
}

$project = Node::load(1);
if (!$project) {
  $project = Node::create([
    'type'                     => 'shp_project',
    'langcode'                 => 'en',
    'uid'                      => '1',
    'status'                   => 1,
    'title'                    => 'Example',
    'field_shp_git_repository' => [['value' => $example_repository]],
    'field_shp_builder_image'  => [['value' => 'uofa/s2i-shepherd-drupal']],
    'field_shp_build_secret'   => [['value' => 'build-key']],
    'field_shp_env_vars'       => [['key' => 'SHEPHERD_INSTALL_PROFILE', 'value' => 'standard']],
  ]);
  $project->save();
}
else {
  echo "Project already setup.\n";
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
    'field_shp_domain'       => 'test-live.' . $domain_name,
    'field_shp_path'         => '/',
    'field_shp_project' => [['target_id' => $project->id()]],
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
    'field_shp_domain'           => 'test-development.' . $domain_name,
    'field_shp_path'             => $site->field_shp_path->value,
    'field_shp_environment_type' => [['target_id' => $development_env->id()]],
    'field_shp_git_reference'    => 'master',
    'field_shp_site'             => [['target_id' => $site->id()]],
    'field_shp_update_on_image_change' => TRUE,
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

/** @var \Drupal\group\Entity\GroupInterface $project_group */
$project_group = \Drupal::service('shp_content_types.group_manager')->load($project);
$project_group->addMember($oc_user, ['group_roles' => ['shp_project-online-consulta']]);
