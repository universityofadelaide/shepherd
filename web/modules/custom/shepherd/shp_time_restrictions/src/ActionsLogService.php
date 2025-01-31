<?php

namespace Drupal\shp_time_restrictions;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
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
   * Constructs an ActionsLog object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Connection $connection) {
    $this->configFactory = $config_factory;
    $this->connection = $connection;
  }

  /**
   * Log node actions.
   */
  public function logAction(NodeInterface $node, string $action) {
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
  public function getLastActionTimestamp() {
    $query = $this->connection->select('shp_time_restrictions_log', 'l')
      ->fields('l', ['timestamp'])
      ->orderBy('timestamp', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return $query ?: NULL;
  }

  /**
   * Returns true if last action was long enough ago.
   *
   * @return bool
   *   true if older enough.
   */
  public function isOutsideEnvironmentCreationTimeDelay(): bool {
    $last_action_timestamp = $this->getLastActionTimestamp();
    $time_delay_config = $this->configFactory->get('shp_time_restrictions.settings')->get('environment_creation_time_delay');
    return time() - $time_delay_config > $last_action_timestamp;
  }

}
