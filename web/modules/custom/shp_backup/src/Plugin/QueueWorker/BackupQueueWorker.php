<?php

namespace Drupal\shp_backup\Plugin\QueueWorker;

class BackupQueueWorker extends BackupQueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // $this->backup->create($data);
  }

}
