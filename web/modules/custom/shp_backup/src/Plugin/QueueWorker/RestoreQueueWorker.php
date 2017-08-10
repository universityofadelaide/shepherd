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
  public function processItem($data) {
    // $this->backup->restore($data['backup'], $data['environment']);
  }

}
