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
 * Create a WCMS "dev" distribution.
 */
$distribution = Node::create([
  'type'                     => 'shp_distribution',
  'langcode'                 => 'en',
  'uid'                      => '1',
  'status'                   => 1,
  'title'                    => 'WCMS D8 Dev Distribution',
  'field_shp_git_repository' => [['value' => 'git@gitlab.adelaide.edu.au:web-team/ua-wcms-d8.git']],
  'field_shp_builder_image'  => [['value' => 'uofa/s2i-shepherd-drupal']],
]);
$distribution->save();

/**
 * Create a WCMS "dev" site.
 *
 * This spawns a "UAT" environment and a corresponding site instance.
 */
$site = Node::create([
  'type'                   => 'shp_site',
  'langcode'               => 'en',
  'uid'                    => '1',
  'status'                 => 1,
  'title'                  => 'wcms site',
  'field_shp_site_title'   => [['value' => 'WCMS Site']],
  'field_shp_distribution' => [['target_id' => $distribution->id()]],
]);
$site->save();
