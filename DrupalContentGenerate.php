<?php

/**
 * @file
 * Contains programmatic creation of Shepherd nodes for use by `drush scr`.
 */

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;
use Drupal\shp_service_accounts\Entity\ServiceAccount;

$etm = \Drupal::entityTypeManager();
$stg = $etm->getStorage('node');
$tstg = $etm->getStorage('taxonomy_term');
$config = \Drupal::configFactory();
$domain_name = getenv("OPENSHIFT_DOMAIN") ?: '192.168.99.100.nip.io';
$openshift_url = getenv("OPENSHIFT_URL") ?: 'https://192.168.99.100:8443';
$example_repository = getenv("DRUPAL_EXAMPLE_REPOSITORY") ?:
  'https://github.com/universityofadelaide/shepherd-example-drupal.git';

$database_host = getenv("DB_HOST") ?: 'mysql-external.' . $domain_name;
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
$db_provisioner_config = $config->getEditable('shp_database_provisioner.settings');
$db_provisioner_config->set('host', $database_host);
$db_provisioner_config->set('port', $database_port);
$db_provisioner_config->save();

// Set orchestration provider config.
$openshift_config = [
  'endpoint'           => $openshift_url,
  'token'              => $token,
  'namespace'          => 'shepherd-dev',
  'site_deploy_prefix' => 'shepherd-dev',
  'verify_tls'         => FALSE,
];
$orchestration_config = $config->getEditable('shp_orchestration.settings');
foreach ($openshift_config as $key => $value) {
  $orchestration_config->set('connection.' . $key, $value);
}
$orchestration_config->set('selected_provider', 'openshift_orchestration_provider');
$orchestration_config->save();

// Set datagrid cache config.
$cache_config = $config->getEditable('shp_cache_backend.settings');
$cache_config->set('namespace', 'shepherd-dev-datagrid');
$cache_config->save();

// Force reload the orchestration plugin to clear the static cache.
Drupal::service('plugin.manager.orchestration_provider')->getProviderInstance(TRUE);

if (!$development = $tstg->loadByProperties(['name' => 'Dev'])) {
  $development_env = Term::create([
    'vid'                   => 'shp_environment_types',
    'name'                  => 'Dev',
    'field_shp_base_domain' => $domain_name,
  ]);
  $development_env->save();

  $production_env = Term::create([
    'vid'  => 'shp_environment_types',
    'name' => 'Prd',
    'field_shp_base_domain' => $domain_name,
    'field_shp_protect' => TRUE,
    'field_shp_update_go_live' => TRUE,
    'field_shp_labels' => [
      [
        'key' => 'type',
        'value' => 'external',
      ],
    ],
  ]);
  $production_env->save();
}
else {
  $development_env = reset($development);
  echo "Taxonomy already setup.\n";
}

// Create a storage class.
if (!$storage = $tstg->loadByProperties(['name' => 'Gold'])) {
  $storage = Term::create([
    'vid' => 'shp_storage_class',
    'name' => 'gold',
  ]);
  $storage->save();
}
else {
  $storage = reset($storage);
  echo "Storage class already setup.\n";
}

// Create config entities for the service accounts if they don't exist.
if (!$service_accounts = $etm->getStorage('service_account')->loadByProperties([])) {
  for ($i = 0; $i <= 4; $i++) {
    $label = sprintf("shepherd-dev-provisioner-00%02d", $i);
    $id = sprintf("shepherd_dev_provisioner_00%02d", $i);

    // This is pretty horrid, but there is no oc command in the dsh shell.
    $token = trim(file_get_contents("../.$label.token"));
    $account = ServiceAccount::create()
      ->set('label', $label)
      ->set('id', $id)
      ->set('status', TRUE)
      ->set('description', "Test provisioner $i")
      ->set('token', $token)
      ->save();
  }
}
else {
  echo "Service accounts already setup.\n";
}

$nodes = $stg->loadByProperties(['title' => 'Drupal example']);

if (!$project = reset($nodes)) {
  $project = Node::create([
    'type'                     => 'shp_project',
    'langcode'                 => 'en',
    'uid'                      => '1',
    'status'                   => 1,
    'title'                    => 'Drupal example',
    'field_shp_git_repository' => [['value' => $example_repository]],
    'field_shp_builder_image'  => [['value' => 'uofa/s2i-shepherd-drupal']],
    'field_shp_build_secret'   => [['value' => 'build-key']],
    'field_shp_env_vars'       => [
      ['key' => 'SHEPHERD_INSTALL_PROFILE', 'value' => 'standard'],
      ['key' => 'MEMCACHE_ENABLED', 'value' => '0'],
      ['key' => 'PUBLIC_DIR', 'value' => '/shared/public'],
      ['key' => 'PRIVATE_DIR', 'value' => '/shared/private'],
      ['key' => 'TMP_DIR', 'value' => '/shared/tmp'],
    ],
    'field_shp_readiness_probe_type' => [['value' => 'tcpSocket']],
    'field_shp_readiness_probe_port' => [['value' => 8080]],
    'field_shp_liveness_probe_type' => [['value' => 'tcpSocket']],
    'field_shp_liveness_probe_port' => [['value' => 8080]],
    'field_shp_cpu_request'    => [['value' => '500m']],
    'field_shp_cpu_limit'      => [['value' => '1000m']],
    'field_shp_memory_request' => [['value' => '256Mi']],
    'field_shp_memory_limit'   => [['value' => '512Mi']],
    // Can't use this with OpenShift Local :-(
    // 'field_shp_storage_class'  => [['target_id' => $storage->id()]],
    'field_shp_backup_size'    => 5,
  ]);
  $project->save();
}
else {
  echo "Project already setup.\n";
}

$nodes = $stg->loadByProperties(['title' => 'Drupal test site']);

if (!$site = reset($nodes)) {
  $site = Node::create([
    'type'                      => 'shp_site',
    'langcode'                  => 'en',
    'uid'                       => '1',
    'status'                    => 1,
    'title'                     => 'Drupal test site',
    'field_shp_short_name'      => 'test',
    'field_shp_domain'          => 'test-live.' . $domain_name,
    'field_shp_git_default_ref' => 'master',
    'field_shp_path'            => '/',
    'field_shp_project'         => [['target_id' => $project->id()]],
    // Can't use this with OpenShift Local :-(
    // 'field_shp_storage_class'   => [['target_id' => $storage->id()]],
    'field_shp_storage_size'  => 5,
  ]);
  $site->moderation_state->value = 'published';
  $site->save();
}
else {
  echo "Site already setup.\n";
}

$nodes = $stg->loadByProperties(['field_shp_domain' => 'test-0.' . $domain_name]);

if (!$env = reset($nodes)) {
  $env = Node::create([
    'type'                       => 'shp_environment',
    'langcode'                   => 'en',
    'uid'                        => '1',
    'status'                     => 1,
    'title'                      => 'Drupal test environment',
    'field_shp_domain'           => 'test-0.' . $domain_name,
    'field_shp_path'             => $site->field_shp_path->value,
    'field_shp_environment_type' => [['target_id' => $development_env->id()]],
    'field_shp_git_reference'    => 'master',
    'field_shp_site'             => [['target_id' => $site->id()]],
    'field_shp_update_on_image_change' => TRUE,
    'field_shp_cron_suspended'   => 1,
    'field_shp_cron_jobs'        => [
      [
        'key' => '*/30 * * * *',
        'value' => 'cd /code; drush -r /code/web cron || true',
      ],
    ],
    'field_shp_cpu_request'    => [['value' => '500m']],
    'field_shp_cpu_limit'      => [['value' => '1000m']],
    'field_shp_memory_request' => [['value' => '256Mi']],
    'field_shp_memory_limit'   => [['value' => '512Mi']],
    'field_cache_backend'      => [
      'plugin_id' => 'memcached_datagrid',
    ],
  ]);
  $env->moderation_state->value = 'published';
  $env->save();
}
else {
  echo "Environment already setup.\n";
}

$oc_user_result = $etm->getStorage('user')->loadByProperties(['name' => 'oc']);

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
