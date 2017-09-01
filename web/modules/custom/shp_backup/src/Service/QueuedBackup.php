<?php

namespace Drupal\shp_backup\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\shp_orchestration\OrchestrationProviderPluginManagerInterface;
use Drupal\shp_orchestration\Service\ActiveJobManager;
use Drupal\shp_orchestration\Service\JobQueue;
use Drupal\token\TokenInterface;

/**
 * Provides a service for accessing the backups.
 *
 * @package Drupal\shp_backup
 */
class QueuedBackup extends Backup {

  /**
   * JobQueue service.
   *
   * @var \Drupal\shp_orchestration\Service\JobQueue
   */
  protected $jobQueue;

  /**
   * Queued Backup constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\token\TokenInterface $token
   *   Token service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\shp_orchestration\Service\ActiveJobManager $activeJobManager
   *   Active job manager.
   * @param \Drupal\shp_orchestration\OrchestrationProviderPluginManagerInterface $pluginManager
   *   The orchestration provider plugin manager.
   * @param \Drupal\shp_orchestration\Service\JobQueue $jobQueue
   *   JobQueue service.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    TokenInterface $token,
    EntityTypeManagerInterface $entityTypeManager,
    ActiveJobManager $activeJobManager,
    OrchestrationProviderPluginManagerInterface $pluginManager,
    JobQueue $jobQueue
  ) {
    parent::__construct(
      $configFactory,
      $token,
      $entityTypeManager,
      $activeJobManager,
      $pluginManager
    );
    $this->jobQueue = $jobQueue;
  }

  /**
   * Create a backup for a backup node.
   *
   * @param \Drupal\node\NodeInterface $backup
   *   The environment to backup.
   *
   * @return bool
   *   True if queued.
   */
  public function create(NodeInterface $backup) {
    $node_storage = $this->entityTypeManager->getStorage('node');
    $environment = $node_storage->load($backup->field_shp_environment->target_id);
    return \Drupal::service('shp_orchestration.job_queue')->add(
      $environment->id(),
      'shp_backup',
      'shp_backup.backup',
      ['backupId' => $backup->id()]
    );
  }

  /**
   * Restore a backup for a given instance.
   *
   * @param \Drupal\node\NodeInterface $backup
   *   The backup to restore.
   * @param \Drupal\node\NodeInterface $environment
   *   The environment to restore.
   *
   * @return bool
   *   True if queued.
   */
  public function restore(NodeInterface $backup, NodeInterface $environment) {
    $node_storage = $this->entityTypeManager->getStorage('node');
    $source_environment = $node_storage->load($backup->field_shp_environment->target_id);
    return \Drupal::service('shp_orchestration.job_queue')->add(
      $source_environment->id(),
      'shp_restore',
      'shp_backup.backup',
      [
        'environmentId' => $environment->id(),
        'backupId' => $backup->id(),
      ]
    );
  }

}
