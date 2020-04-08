<?php

namespace Drupal\shp_backup\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\shp_orchestration\OrchestrationProviderPluginManagerInterface;

/**
 * Provides a service for accessing the backups.
 *
 * @package Drupal\shp_backup
 */
class Backup {

  use StringTranslationTrait;

  /**
   * The orchestration provider plugin manager.
   *
   * @var \Drupal\shp_orchestration\OrchestrationProviderInterface
   */
  protected $orchestrationProvider;

  /**
   * Backup constructor.
   *
   * @param \Drupal\shp_orchestration\OrchestrationProviderPluginManagerInterface $pluginManager
   *   The orchestration provider plugin manager.
   */
  public function __construct(OrchestrationProviderPluginManagerInterface $pluginManager) {
    $this->orchestrationProvider = $pluginManager->getProviderInstance();
  }

  /**
   * Gets a list of backups for all the environments of a site.
   *
   * @param \Drupal\node\NodeInterface $site
   *   Site node to retrieve the list of backups for.
   *
   * @return \UniversityOfAdelaide\OpenShift\Objects\Backups\BackupList|bool
   *   The backup list if successful otherwise false.
   */
  public function getAllForSite(NodeInterface $site) {
    return $this->orchestrationProvider->getBackupsForSite($site->id());
  }

  /**
   * Gets a list of backups for an environment.
   *
   * @param \Drupal\node\NodeInterface $environment
   *   Environment node to retrieve the list of backups for.
   *
   * @return \UniversityOfAdelaide\OpenShift\Objects\Backups\BackupList|bool
   *   The backup list if successful otherwise false.
   */
  public function getAllForEnvironment(NodeInterface $environment) {
    return $this->orchestrationProvider->getBackupsForEnvironment($environment->id());
  }

  /**
   * Create a backup node for a given site and environment.
   *
   * @param \Drupal\node\NodeInterface $site
   *   The site the environment blongs to.
   * @param \Drupal\node\NodeInterface $environment
   *   The environment to create the backup node for.
   * @param string $friendly_name
   *   An optional friendly name to set on the backup.
   *
   * @return \UniversityOfAdelaide\OpenShift\Objects\Backups\Backup|bool
   *   The backup object, or false.
   */
  public function createBackup(NodeInterface $site, NodeInterface $environment, string $friendly_name = '') {
    $result = $this->orchestrationProvider->backupEnvironment(
      $site->id(),
      $environment->id(),
      $friendly_name
    );

    return $result;
  }

  /**
   * Restore a backup for a given instance.
   *
   * @param string $backup_name
   *   The name of the backup to restore.
   * @param \Drupal\node\NodeInterface $environment
   *   The environment to restore.
   *
   * @return bool
   *   True on success.
   */
  public function restore(string $backup_name, NodeInterface $environment) {
    $site = $environment->field_shp_site->entity;
    $result = $this->orchestrationProvider->restoreEnvironment(
      $backup_name,
      $site->id(),
      $environment->id()
    );

    return $result;
  }

  /**
   * Sync an environment.
   *
   * @param string $site_id
   *   The site id.
   * @param string $from_environment_id
   *   The environment to sync from.
   * @param string $to_environment_id
   *   The environment to sync to.
   *
   * @return bool
   *   Whether it was a success.
   */
  public function sync(string $site_id, string $from_environment_id, string $to_environment_id) {
    return $this->orchestrationProvider->syncEnvironment($site_id, $from_environment_id, $to_environment_id) ? TRUE : FALSE;
  }

  /**
   * Apply alterations to entity operations.
   *
   * @param array $operations
   *   List of operations.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to apply operations to.
   */
  public function operationsLinksAlter(array &$operations, EntityInterface $entity) {
    $site = $entity->field_shp_site->target_id;
    $environment = $entity->id();

    $modal_attributes = [
      'class' => ['button', 'use-ajax'],
      'data-dialog-type' => 'modal',
      'data-dialog-options' => Json::encode([
        'width' => '50%',
        'height' => '50%',
      ]),
    ];
    $operations['backup'] = [
      'title' => $this->t('Backup'),
      'weight' => 1,
      'url' => Url::fromRoute('shp_backup.environment-backup-form', ['site' => $site, 'environment' => $environment]),
      'attributes' => $modal_attributes,
    ];

    if (($environment_term = $entity->field_shp_environment_type->entity) && !$environment_term->field_shp_protect->value) {
      $operations['restore'] = [
        'title'      => $this->t('Restore'),
        'weight'     => 2,
        'url'        => Url::fromRoute('shp_backup.environment-restore-form', [
          'site'        => $site,
          'environment' => $environment,
        ]),
        'attributes' => $modal_attributes,
      ];
    }
  }

}
