<?php

namespace Drupal\shp_backup\Plugin\QueueWorker;

/**
 * Triggers the backup of an environment.
 *
 * @QueueWorker(
 *   id = "shp_backup",
 *   title = @Translation("Environment backup."),
 * )
 */
class BackupQueueWorker extends BackupQueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($job) {
    if ($node = $this->nodeStorage->load($job->entityId)) {
      $this->backup->create($node);
    }
  }

}
