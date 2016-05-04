<?php

/**
 * @file
 * Contains Drupal\ua_sm_custom\Service\Backup.
 */

namespace Drupal\ua_sm_custom\Service;

use Drupal\Core\Config\ConfigFactory;

/**
 * Provides a service for accessing the backups.
 *
 * @package Drupal\ua_sm_custom
 */
class Backup {

  private $config;

  /**
   * Drupal\Core\Config\ConfigFactory definition.
   *
   * @var Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Backup constructor.
   *
   * @param ConfigFactory $config_factory
   *   Config Factory.
   */
  public function __construct(ConfigFactory $config_factory) {
    $this->configFactory = $config_factory;
    $this->config = $this->configFactory->get('ua_sm_custom.settings')->get('backup_service');
  }

  /**
   * Gets a list of backups by scan dir and filter.
   *
   * @param int $site
   *    Site id.
   * @param int $environment
   *    Environment id.
   */
  public function get($site, $environment) {
    $path = $this->config['path'] . "$site/$environment";
    return array_diff(scandir($path), ['.', '..']);
  }

}
