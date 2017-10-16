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
    // Load the node for the job.
    if ($backup = $this->nodeStorage->load($job->backupId)) {
      // Perform the job.
      if ($responseBody = $this->backup->create($backup)) {
        // Update the job name for isComplete() check.
        $this->setJobName($job, $responseBody);
      }
    }
  }

}
