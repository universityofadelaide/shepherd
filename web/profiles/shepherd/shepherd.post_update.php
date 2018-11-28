<?php

/**
 * @file
 * Post update hooks for the Shepherd profile.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Helper function to import config as defined on disk.
 *
 * @param string $entity_type
 *   The entity type id.
 * @param string $bundle
 *   The bundle.
 * @param string $field_name
 *   The field name.
 */
function _shp_import_config_from_disk($entity_type, $bundle, $field_name) {
  $directory = dirname(DRUPAL_ROOT) . '/config-export/';
  // Create the field and storage direct from their config-export (we're running
  // this before config import).
  if (!FieldStorageConfig::load(sprintf('%s.%s', $entity_type, $field_name))) {
    $config = Yaml::decode(file_get_contents(sprintf('%s/field.storage.%s.%s.yml', $directory, $entity_type, $field_name)));
    // Decode allowed values as per ListItemBase::simplifyAllowedValues().
    if (isset($config['settings']['allowed_values'])) {
      $config['settings']['allowed_values'] = array_reduce($config['settings']['allowed_values'], function ($carry, $item) {
        $carry[$item['value']] = $item['value'];
        return $carry;
      }, []);
    }
    $storage = FieldStorageConfig::create($config);
    $storage->save();
    \Drupal::entityDefinitionUpdateManager()->installFieldStorageDefinition($field_name, $entity_type, $bundle, $storage);
  }
  if (!FieldConfig::load(sprintf('%s.%s.%s', $entity_type, $bundle, $field_name))) {
    $field = FieldConfig::create(Yaml::decode(file_get_contents(sprintf('%s/field.field.%s.%s.%s.yml', $directory, $entity_type, $bundle, $field_name))));
    $field->save();
  }
  \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
}

/**
 * Enable drush_cmi_tools.
 */
function shepherd_post_update_enable_drush_cmi_tools() {
  \Drupal::service('module_installer')->install(['drush_cmi_tools']);
}

/**
 * Populate environment volume snapshot values..
 */
function shepherd_post_update_populate_field_volume_snapshots(&$sandbox) {
  $node_storage = \Drupal::entityTypeManager()->getStorage('node');
  if (!isset($sandbox['progress'])) {
    _shp_import_config_from_disk('node', 'shp_environment', 'field_shp_volume_snapshots');
    $query = $node_storage->getQuery()
      ->condition('type', 'shp_environment')
      ->notExists('field_shp_volume_snapshots');
    $sandbox['ids'] = $query->execute();

    $sandbox['progress'] = 0;
    $sandbox['max'] = count($sandbox['ids']);
  }
  $ids = array_splice($sandbox['ids'], 0, 50);
  foreach ($node_storage->loadMultiple($ids) as $node) {
    $node->set('field_shp_volume_snapshots', 1);
    $node->setNewRevision(FALSE);
    $node->save();
  }
  $sandbox['progress'] += count($ids);
  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
}
