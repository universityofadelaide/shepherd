<?php

namespace Drupal\shp_content_migration\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate_drupal_d8\Plugin\migrate\source\d8\ContentEntity;

/**
 * Migration of invoice nodes from drupal6 erp system.
 *
 * @MigrateSource(
 *   id = "shp_environment",
 * )
 */
class Environment extends ContentEntity {

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function prepareRow(Row $row): bool {
    if (parent::prepareRow($row) === FALSE) {
      return FALSE;
    }

    // Force the cache backend to the new datagrid.
    $row->setSourceProperty('field_cache_backend', ['plugin_id' => 'memcached_datagrid_yml']);

    return TRUE;
  }

}
