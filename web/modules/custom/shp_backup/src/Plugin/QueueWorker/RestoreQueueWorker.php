<?php

namespace Drupal\shp_backup\Plugin\QueueWorker;

class RestoreQueueWorker extends BackupQueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // $this->backup->restore($data['backup'], $data['environment']);
  }

}
