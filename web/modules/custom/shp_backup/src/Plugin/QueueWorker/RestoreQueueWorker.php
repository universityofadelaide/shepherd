<?php

namespace Drupal\shp_backup\Plugin\QueueWorker;

/**
 * Triggers the restore of a backup to an environment.
 *
 * @QueueWorker(
 *   id = "shp_restore",
 *   title = @Translation("Restore a backup to an environment."),
 * )
 */
class RestoreQueueWorker extends BackupQueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($job) {
    if ($node = $this->nodeStorage->load($job->entityId)) {
      $environment = $this->nodeStorage->load($job->environment);
      $this->backup->restore($node, $environment);
    }
  }

}
