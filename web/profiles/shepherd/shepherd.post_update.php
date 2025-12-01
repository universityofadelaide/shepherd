<?php

/**
 * @file
 * Post update hooks for the Shepherd profile.
 */

use Drupal\Core\Serialization\Yaml;
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
 * Batch update environments and add the cache backend field.
 */
function shepherd_post_update_add_cache_backend(&$sandbox) {
  $entity_storage = \Drupal::entityTypeManager()->getStorage('node');
  if (!isset($sandbox['progress'])) {
    \Drupal::state()->set('shp_orchestration_update_killswitch', TRUE);
    \Drupal::service('module_installer')->install(['plugin']);
    _shp_import_config_from_disk('node', 'shp_environment', 'field_cache_backend');
    $query = $entity_storage->getQuery()
      ->condition('type', 'shp_environment');
    $sandbox['ids'] = $query->accessCheck(FALSE)->execute();
    $sandbox['progress'] = 0;
    $sandbox['max'] = count($sandbox['ids']);
  }
  $ids = array_splice($sandbox['ids'], 0, 50);

  /** @var \Drupal\node\NodeInterface $node */
  foreach ($entity_storage->loadMultiple($ids) as $node) {
    $node->set('field_cache_backend', [
      'plugin_id' => 'redis',
    ]);
    $node->setNewRevision(FALSE);
    $node->save();
  }
  $sandbox['progress'] += count($ids);

  // Reset caches.
  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['progress'] / $sandbox['max']);
  if ($sandbox['#finished'] === 1) {
    \Drupal::state()->delete('shp_orchestration_update_killswitch');
  }
}

/**
 * Enable views_bulk_operations.
 */
function shepherd_post_update_enable_views_bulk_operations() {
  \Drupal::service('module_installer')->install(['views_bulk_operations']);
}

/**
 * Enable config_ignore.
 */
function shepherd_post_update_enable_config_ignore() {
  \Drupal::service('module_installer')->install(['config_ignore']);
}


/**
 * Enable ckeditor5.
 */
function shepherd_post_update_migrate_ckeditor() {
  \Drupal::service('module_installer')->uninstall(['ckeditor']);
  \Drupal::service('module_installer')->install(['ckeditor5']);
}

/**
 * Install Claro admin theme and uninstall Seven.
 */
function shepherd_post_update_change_theme(&$sandbox = NULL) {
  // Load theme extension list to check filesystem existence.
  /** @var \Drupal\Core\Extension\ExtensionList $theme_extension_list */
  $theme_extension_list = \Drupal::service('extension.list.theme');

  // --- 1. Ensure Claro exists in filesystem ---
  if (!$theme_extension_list->get('claro')) {
    throw new \RuntimeException("The Claro theme is not present in the filesystem.");
  }

  // Services used later.
  /** @var \Drupal\Core\Extension\ThemeInstallerInterface $theme_installer */
  $theme_installer = \Drupal::service('theme_installer');
  /** @var \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler */
  $theme_handler = \Drupal::service('theme_handler');

  // Get installed themes.
  $installed_themes = $theme_handler->listInfo();

  // --- 2. Install Claro if not installed ---
  if (!isset($installed_themes['claro'])) {
    $theme_installer->install(['claro']);
  }

  // --- 3. Set Claro as admin theme ---
  $config = \Drupal::configFactory()->getEditable('system.theme');
  $config->set('admin', 'claro')->save();

  // --- 4. Uninstall Seven if installed ---
  if (isset($installed_themes['seven'])) {
    // Make sure Seven is not set as default/admin before uninstall.
    $default_theme = $config->get('default');
    $admin_theme   = $config->get('admin');

    if ($default_theme === 'seven') {
      // Prevent accidental breakage.
      throw new \RuntimeException("Seven cannot be uninstalled because it is the default theme.");
    }
    if ($admin_theme === 'seven') {
      // Should never happen after setting Claro, but safe guard.
      $config->set('admin', 'claro')->save();
    }

    // Uninstall Seven.
    $theme_installer->uninstall(['seven']);
  }

  return t('Claro installed and enabled as admin theme; Seven uninstalled.');
}
