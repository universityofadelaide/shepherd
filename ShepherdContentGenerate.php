<?php
/**
 * @file
 * Contains programmatic creation of Shepherd nodes for use by `drush scr`.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;
use Drupal\shp_orchestration\Entity\OpenShiftConfigEntity;

$domain_name = getenv("DOMAIN");
if (empty($domain_name)) {
  $domain_name = '192.168.99.100.nip.io';
}
$token = getenv("TOKEN");

/**
 * Override production default values for local dev.
 */
$environment_defaults = [
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

if (!empty($token)) {
  /**
   * Create an openshift endpoint
   */
  $openshift = OpenShiftConfigEntity::create([
    'endpoint'  => 'https://192.168.99.100:8443/',
    'token'     => $token,
    'namespace' => 'myproject',
    'mode'      => 'dev',
    'id'        => 'openshift',
  ]);
  $openshift->save();

  /**
   * Create a WCMS "dev" distribution.
   */
  $distribution = Node::create([
    'type'                     => 'shp_distribution',
    'langcode'                 => 'en',
    'uid'                      => '1',
    'status'                   => 1,
    'title'                    => 'WCMS D8',
    'field_shp_git_repository' => [['value' => 'git@gitlab.adelaide.edu.au:web-team/ua-wcms-d8.git']],
    'field_shp_builder_image'  => [['value' => 'uofa/s2i-shepherd-drupal']],
    'field_shp_ssh_key'        => [['value' => 'build-key']],
  ]);
  $distribution->save();

  /**
   * Create a WCMS "dev" site.
   *
   */
  $site = Node::create([
    'type'                   => 'shp_site',
    'langcode'               => 'en',
    'uid'                    => '1',
    'status'                 => 1,
    'title'                  => 'wcms-d8-site',
    'field_shp_namespace'    => 'myproject',
    'field_shp_site_title'   => [['value' => 'WCMS D8 Site']],
    'field_shp_distribution' => [['target_id' => $distribution->id()]],
  ]);
  $site->save();

  /**
   * Create a WCMS "dev" environment.
   */
  $env = Node::create([
    'type'                      => 'shp_environment',
    'langcode'                  => 'en',
    'uid'                       => '1',
    'status'                    => 1,
    'title'                     => 'wcms-d8',
    'field_shp_deployment_name' => 'wcms-d8-dev-environment',
    'field_shp_domain_name'     => $domain_name,
    'field_shp_git_reference'   => 'master',
    'field_shp_site'            => [['target_id' => $site->id()]],
    'field_shp_custom_config'   => '',
  ]);
  $env->save();
}
else {
  echo "Better results would be achieved by specifying a token, eg.";
  echo "TOKEN=output_from_oc_whoami_-t bin/drush -r web scr ShepherdContentGenerate.php --uri=shepherd.test";
}
