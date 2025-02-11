<?php

namespace Drupal\shp_time_restrictions;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
<<<<<<< HEAD
use Drupal\Core\KeyValueStore\KeyValueExpirableFactory;
=======
>>>>>>> 8eec280068c7240555b3bc6a983dd220643459e1
use Drupal\node\NodeInterface;

/**
 * Service description.
 */
class ActionsLogService {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
<<<<<<< HEAD
   * The KeyValue interface.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface|mixed
   */
  protected $keyvalue;

  /**
=======
>>>>>>> 8eec280068c7240555b3bc6a983dd220643459e1
   * Constructs an ActionsLog object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
<<<<<<< HEAD
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactory $keyValueExpirableFactory
   *   The KeyValueExpirableFactory.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Connection $connection, KeyValueExpirableFactory $keyValueExpirableFactory) {
    $this->configFactory = $config_factory;
    $this->connection = $connection;
    $this->keyvalue = $keyValueExpirableFactory->get('shp_time_restrictions');

=======
   */
  public function __construct(ConfigFactoryInterface $config_factory, Connection $connection) {
    $this->configFactory = $config_factory;
    $this->connection = $connection;
>>>>>>> 8eec280068c7240555b3bc6a983dd220643459e1
  }

  /**
   * Log node actions.
   */
  public function logAction(NodeInterface $node, string $action) {
<<<<<<< HEAD
    $time_delay_config = $this->configFactory->get('shp_time_restrictions.settings')->get('environment_creation_time_delay');
    $this->keyvalue->setWithExpire($node->id(),
      [
        'action' => $action,
        'node' => $node->id(),
        'expiry' => \Drupal::time()->getRequestTime() + $time_delay_config,
      ], $time_delay_config);

  }

  /**
   * Get the next expiry time.
   *
   * @param int|null $nid
   *   Node ID.
   *
   * @return int
   *   Timestamp of next expiry.
   */
  public function getNextExpiration(?int $nid = NULL) {
    if ($nid) {
      $expiration = $this->keyvalue->get($nid);
    }
    else {
      $expirations = $this->keyvalue->getAll();
      $expiration = array_slice($expirations, -1)[0] ?? NULL;
    }

    return $expiration['expiry'];

=======
    $this->connection->insert('shp_time_restrictions_log')
      ->fields([
        'nid' => $node->id(),
        'action' => $action,
        'timestamp' => \Drupal::time()->getRequestTime(),
      ])
      ->execute();
  }

  /**
   * Returns the timestamp of the action.
   *
   * @return int|null
   *   integer of timestamp.
   *
   * @throws \Exception
   */
  public function getLastActionTimestamp(?int $nid = NULL) {
    $query = $this->connection->select('shp_time_restrictions_log', 'l')
      ->fields('l', ['timestamp']);
    if ($nid) {
      $query->condition('nid', $nid);
    }

    $query->orderBy('timestamp', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    $timestamp = $query->execute()->fetchField();

    return $timestamp !== FALSE ? (int) $timestamp : NULL;
>>>>>>> 8eec280068c7240555b3bc6a983dd220643459e1
  }

  /**
   * Returns true if last action was long enough ago.
   *
   * @return bool
   *   true if older enough.
   */
  public function isOutsideEnvironmentCreationTimeDelay(?int $nid = NULL): bool {
<<<<<<< HEAD
    $nids = $this->keyvalue->getAll();

    // No actions within time delay.
    if (empty($nids)) {
      return TRUE;
    }

    // No actions for this node within time delay.
    if (isset($nid) && !in_array($nid, array_keys($nids))) {
      return TRUE;
    }

    return FALSE;
=======
    $last_action_timestamp = $this->getLastActionTimestamp($nid);
    $time_delay_config = $this->configFactory->get('shp_time_restrictions.settings')->get('environment_creation_time_delay');

    if ($last_action_timestamp) {
      return time() - $time_delay_config > $last_action_timestamp;
    }

    return TRUE;
>>>>>>> 8eec280068c7240555b3bc6a983dd220643459e1
  }

}
