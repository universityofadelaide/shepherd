<?php

/**
 * @file
 * Contains programmatic creation of Wordpress nodes for use by `drush scr`.
 */

use Drupal\node\Entity\Node;
use Drupal\shp_orchestration\Entity\OpenShiftConfigEntity;
use Drupal\shp_redis_support\Entity\OpenShiftWithRedisConfigEntity;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;

$domain_name = getenv("OPENSHIFT_DOMAIN") ?: '192.168.99.100.nip.io';
$openshift_url = getenv("OPENSHIFT_URL") ?: 'https://192.168.99.100:8443';
$example_repository = getenv("DRUPAL_EXAMPLE_REPOSITORY") ?:
    'https://github.com/singularo/shepherd-example-wordpress.git';

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

$orchestration_config = \Drupal::service('config.factory')->getEditable('shp_orchestration.settings');
$orchestration_config->set('selected_provider', 'openshift_orchestration_provider');
$orchestration_config->save();

// Force reload the orchestration plugin to clear the static cache.
Drupal::service('plugin.manager.orchestration_provider')->getProviderInstance(TRUE);

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
    ->loadByProperties(['title' => 'Wordpress example']);

if (!$project = reset($nodes)) {
  $project = Node::create([
    'type'                     => 'shp_project',
    'langcode'                 => 'en',
    'uid'                      => '1',
    'status'                   => 1,
    'title'                    => 'Wordpress example',
    'field_shp_git_repository' => [['value' => $example_repository]],
    'field_shp_builder_image'  => [['value' => 'uofa/s2i-shepherd-wordpress']],
    'field_shp_build_secret'   => [['value' => 'build-key']],
    'field_shp_env_vars'       => [],
  ]);
  $project->save();
}
else {
  echo "Project already setup.\n";
}

$nodes = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->loadByProperties(['title' => 'Wordpress test site']);

if (!$site = reset($nodes)) {
  $site = Node::create([
    'type'                      => 'shp_site',
    'langcode'                  => 'en',
    'uid'                       => '1',
    'status'                    => 1,
    'title'                     => 'Wordpress test Site',
    'field_shp_namespace'       => 'myproject',
    'field_shp_short_name'      => 'wordpress-test',
    'field_shp_domain'          => 'wordpress-test-live.' . $domain_name,
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

$ndoes = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->loadByProperties(['title' => 'wordpress-test-development.' . $domain_name]);

if (!$env = reset($nodes)) {
  $env = Node::create([
    'type'                       => 'shp_environment',
    'langcode'                   => 'en',
    'uid'                        => '1',
    'status'                     => 1,
    'title'                      => 'Wordpress test environment',
    'field_shp_domain'           => 'wordpress-test-development.' . $domain_name,
    'field_shp_path'             => $site->field_shp_path->value,
    'field_shp_environment_type' => [['target_id' => $development_env->id()]],
    'field_shp_git_reference'    => 'master',
    'field_shp_site'             => [['target_id' => $site->id()]],
    'field_shp_update_on_image_change' => TRUE,
    'field_shp_cron_suspended'   => 1,
    'field_shp_cron_jobs'        => []
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

