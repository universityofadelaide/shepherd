<?php

namespace Drupal\shp_custom\Service;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityInterface;
use GuzzleHttp\Client;

/**
 * Provides a service for accessing the backups.
 *
 * @package Drupal\shp_custom
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
    $this->config = $this->configFactory->get('shp_custom.settings')->get('backup_service');
    $this->jobRunner = $job_runner;
  }

  /**
   * Gets a list of backups for a specific environment.
   *
   * @param int $site
   *    Site id.
   * @param int $environment
   *    Optional Environment id.
   *
   * @return array
   *   An array of backup folders.
   */
  public function get($site, $environment) {
    $path = $this->config['path'] . "/$site/$environment";
    return array_values(array_diff(scandir($path), ['.', '..']));
  }

  /**
   * Gets a sorted list of backups for all the environments of a site.
   *
   * @param int $site
   *    Site id.
   *
   * @return array
   *   An array of backup folders.
   */
  public function getAll($site) {
    $path = $this->config['path'] . "/$site";
    $environments = array_diff(scandir($path), ['.', '..']);
    $backups = [];
    foreach ($environments as $environment) {
      foreach ($this->get($site, $environment) as $backup) {
        $backups[] = [
          'environment' => $environment,
          'backup' => $backup,
        ];
      }
    }

    // Sort backups by date descending.
    uasort($backups, function ($a, $b) {
      if ($a['backup'] == $b['backup']) {
        return 0;
      }
      return ($a['backup'] > $b['backup']) ? -1 : 1;
    });

    // Make the array keys pretty.
    return array_values($backups);
  }

  /**
   * Create a backup for a given instance.
   *
   * @param EntityInterface $instance
   *   The instance to backup.
   *
   * @TODO: Shepherd: Instances no longer exist.
   */
  public function createBackup(EntityInterface $instance) {
    // @TODO: Shepherd: Replace the JenkinsClient call with call to some other runner?
    // $this->jobRunner->job(JenkinsClient::BACKUP_JOB, $instance);
  }

  /**
   * Restore a backup for a given instance.
   *
   * @param EntityInterface $instance
   *   The instance to restore to.
   *
   * @TODO: Shepherd: Instances no longer exist.
   */
  public function restore(EntityInterface $instance) {
    // @TODO: Shepherd: Replace the JenkinsClient call with call to some other runner?
    // $this->jobRunner->job(JenkinsClient::RESTORE_JOB, $instance);
  }

}
