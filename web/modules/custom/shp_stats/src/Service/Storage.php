<?php

namespace Drupal\shp_stats\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class Storage
 * @package Drupal\shp_stats\Service
 */
class Storage {

  use StringTranslationTrait;

  protected $tableName = 'shp_stats_active';

  protected $database;

  /**
   * Storage constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * @param array $entry
   * @return \Drupal\Core\Database\StatementInterface|int|null
   */
  public function insert(array $entry) {
    $return_value = NULL;
    try {
      $return_value = $this->database->insert($this->tableName)
        ->fields($entry)
        ->execute();
    }
    catch (\Exception $e) {
      drupal_set_message($this->t('Database insert failed. Message = %message'), [
        '%message' => $e->getMessage(),
      ]);
    }
    return $return_value;
  }

  /**
   * Update an entry in the database.
   *
   * @param array $entry
   *   An array containing all the fields of the item to be updated.
   *
   * @return int
   *   The number of updated rows.
   *
   * @see db_update()
   */
  public function update(array $entry) {
    $count = NULL;
    try {
      $count = $this->database->update($this->tableName)
        ->fields($entry)
        ->condition('id', $entry['id'])
        ->execute();
    }
    catch (\Exception $e) {
      drupal_set_message(t('Database update failed. Message = %message', [
        '%message' => $e->getMessage(),
      ]), 'error');
    }
    return $count;
  }

  /**
   * @param array $entry
   * @return mixed
   */
  public function load(array $entry = []) {
    $select = $this->database->select($this->tableName, 'stats');
    $select->fields('stats');

    foreach ($entry as $field => $value) {
      $select->condition($field, $value);
    }
    return $select->execute()->fetchAll();
  }

}
