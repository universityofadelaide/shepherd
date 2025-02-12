<?php

namespace Drupal\shp_time_restrictions;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactory;
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
   * The KeyValue interface.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface|mixed
   */
  protected $keyvalue;

  /**
   * Constructs an ActionsLog object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactory $keyValueExpirableFactory
   *   The KeyValueExpirableFactory.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Connection $connection, KeyValueExpirableFactory $keyValueExpirableFactory) {
    $this->configFactory = $config_factory;
    $this->connection = $connection;
    $this->keyvalue = $keyValueExpirableFactory->get('shp_time_restrictions');

  }

  /**
   * Log node actions.
   */
  public function logAction(NodeInterface $node, string $action) {
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
      // There should only be one item in the key value table, get the last one.
      $expiration = array_slice($expirations, -1)[0] ?? NULL;
    }

    return $expiration['expiry'];

  }

  /**
   * Returns true if last action was long enough ago.
   *
   * @return bool
   *   true if older enough.
   */
  public function isOutsideEnvironmentCreationTimeDelay(?int $nid = NULL): bool {
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
  }

}
