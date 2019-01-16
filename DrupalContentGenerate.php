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
$example_repository = getenv("DRUPAL_EXAMPLE_REPOSITORY") ?:
    'https://github.com/universityofadelaide/shepherd-example-drupal.git';

$database_host = getenv("DB_HOST") ?: 'mysql-myproject.' . $domain_name;
$database_port = getenv("DB_PORT") ?: '31632';

// Check that required variables are actually set.
$token = trim(getenv("TOKEN"));

if (empty($token)) {
  echo "To generate default configuration for development, the TOKEN variable is required to be set.\n";
  echo "Export your auth TOKEN from your host with.\n";
  echo "export TOKEN=some-token\n";
  echo "You can then safely re-run `robo dev:drupal-content-generate`\n";
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

$nodes = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->loadByProperties(['title' => 'Drupal example']);

if (!$project = reset($nodes)) {
  $project = Node::create([
    'type'                     => 'shp_project',
    'langcode'                 => 'en',
    'uid'                      => '1',
    'status'                   => 1,
    'title'                    => 'Example',
    'field_shp_git_repository' => [['value' => $example_repository]],
    'field_shp_builder_image'  => [['value' => 'uofa/s2i-shepherd-drupal']],
    'field_shp_build_secret'   => [['value' => 'build-key']],
    'field_shp_env_vars'       => [
      ['key' => 'SHEPHERD_INSTALL_PROFILE', 'value' => 'standard'],
      ['key' => 'REDIS_ENABLED', 'value' => '0'],
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
}
else {
  echo "Project already setup.\n";
}

$nodes = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->loadByProperties(['title' => 'Drupal test site']);

if (!$site = reset($nodes)) {
  $site = Node::create([
    'type'                      => 'shp_site',
    'langcode'                  => 'en',
    'uid'                       => '1',
    'status'                    => 1,
    'title'                     => 'Drupal Test Site',
    'field_shp_namespace'       => 'myproject',
    'field_shp_short_name'      => 'test',
    'field_shp_domain'          => 'test-live.' . $domain_name,
    'field_shp_git_default_ref' => 'master',
    'field_shp_path'            => '/',
    'field_shp_project'         => [['target_id' => $project->id()]],
  ]);
  $site->moderation_state->value = 'published';
  $site->save();
}
else {
  echo "Site already setup.\n";
}

$nodes = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->loadByProperties(['field_shp_domain' => 'drupal-test-development.' . $domain_name]);

if (!$env = reset($nodes)) {
  $env = Node::create([
    'type'                       => 'shp_environment',
    'langcode'                   => 'en',
    'uid'                        => '1',
    'status'                     => 1,
    'title'                      => 'Drupal test environment',
    'field_shp_domain'           => 'drupal-test-development.' . $domain_name,
    'field_shp_path'             => $site->field_shp_path->value,
    'field_shp_environment_type' => [['target_id' => $development_env->id()]],
    'field_shp_git_reference'    => 'master',
    'field_shp_site'             => [['target_id' => $site->id()]],
    'field_shp_update_on_image_change' => TRUE,
    'field_shp_cron_suspended'   => 1,
    'field_shp_cron_jobs'        => [
      ['key' => '*/30 * * * *', 'value' => 'cd /code; drush -r /code/web cron || true'],
    ]
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
