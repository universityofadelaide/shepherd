<?php

namespace Drupal\shp_custom\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\shp_orchestration\Exception\OrchestrationProviderNotConfiguredException;


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
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The job runner is used to trigger the backup process.
   *
   * @var \GuzzleHttp\Client
   */
  protected $jobRunner;

  /**
   * Backup constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
    $this->config = $this->configFactory->get('shp_custom.settings')->get('backup_service');
  }

  /**
   * Gets a list of backups for a specific environment.
   *
   * @param int $site
   *   Site id.
   * @param int $environment
   *   Optional Environment id.
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
   *   Site id.
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
   * Create a backup for a given environment.
   *
   * @param \Drupal\Core\Entity\EntityInterface $site
   *   The site to backup.
   * @param string $distribution_name,
   *   The distribution name that the environment is build from.
   * @param \Drupal\Core\Entity\EntityInterface $environment
   *   The environment to backup.
   *
   * @return bool
   */
  public function createBackup(EntityInterface $site, string $distribution_name, EntityInterface $environment) {
    try {
      /** @var \Drupal\shp_orchestration\OrchestrationProviderInterface $orchestration_provider_plugin */
      $orchestration_provider_plugin = \Drupal::service('plugin.manager.orchestration_provider')
        ->getProviderInstance();
    }
    catch (OrchestrationProviderNotConfiguredException $e) {
      drupal_set_message($e->getMessage(), 'warning');
      return FALSE;
    }

    $config = \Drupal::configFactory()->getEditable('shp_custom.settings');
    $backup_command = implode(" && ", explode("\n", $config->get('backup_service.backup_command')));

    $result = $orchestration_provider_plugin->backupEnvironment(
      $distribution_name,
      $site->field_shp_short_name->value,
      $environment->id(),
      $environment->field_shp_git_reference->value,
      $backup_command
    );

    return $result;
  }

  /**
   * Restore a backup for a given instance.
   *
   * @param \Drupal\Core\Entity\EntityInterface $site
   *   The site to restore.
   * @param string $distribution_name,
   *   The distribution name that the environment is build from.
   * @param \Drupal\Core\Entity\EntityInterface $environment
   *   The environment to backup.
   *
   * @return bool
   */
  public function restore(EntityInterface $site, string $distribution_name, EntityInterface $environment) {
    try {
      /** @var \Drupal\shp_orchestration\OrchestrationProviderInterface $orchestration_provider_plugin */
      $orchestration_provider_plugin = \Drupal::service('plugin.manager.orchestration_provider')
        ->getProviderInstance();
    }
    catch (OrchestrationProviderNotConfiguredException $e) {
      drupal_set_message($e->getMessage(), 'warning');
      return FALSE;
    }

    $config = \Drupal::configFactory()->getEditable('shp_custom.settings');
    $backup_command = implode(" && ", explode("\n", $config->get('backup_service.restore_command')));

    $result = $orchestration_provider_plugin->restoreEnvironment(
      $distribution_name,
      $site->field_shp_short_name->value,
      $environment->id(),
      $environment->field_shp_git_reference->value,
      $backup_command
    );

    return $result;
  }

}
