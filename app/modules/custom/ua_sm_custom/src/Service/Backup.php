<?php

namespace Drupal\ua_sm_custom\Service;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityInterface;
use GuzzleHttp\Client;

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
   * The job runner is used to trigger the backup process.
   *
   * @var GuzzleHttp\Client
   */
  protected $jobRunner;

  /**
   * Backup constructor.
   *
   * @param ConfigFactory $config_factory
   *   Config factory.
   * @param Client $job_runner
   *   Job runner.
   */
  public function __construct(ConfigFactory $config_factory, Client $job_runner) {
    $this->configFactory = $config_factory;
    $this->config = $this->configFactory->get('ua_sm_custom.settings')->get('backup_service');
    $this->jobRunner = $job_runner;
  }

  /**
   * Gets a list of backups by scan dir and filter.
   *
   * @param int $site
   *    Site id.
   * @param int $environment
   *    Optional Environment id.
   *
   * @return array
   *   An array of backup files.
   */
  public function get($site, $environment = NULL) {
    $path = is_null($environment) ? $this->config['path'] . $site :
      $this->config['path'] . "$site/$environment";
    return array_diff(scandir($path), ['.', '..']);
  }

  /**
   * Create a backup for a given instance.
   *
   * @param EntityInterface $instance
   *   The instance to backup.
   */
  public function createBackup(EntityInterface $instance) {
    $this->jobRunner->job('backup', $instance);
  }

}
