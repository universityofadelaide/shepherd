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
    // Load the node for the job.
    if (($backup = $this->nodeStorage->load($job->backupId)) &&
      ($environment = $this->nodeStorage->load($job->environmentId))) {
      // Perform the job.
      if ($responseBody = $this->backup->restore($backup, $environment)) {
        // Update the job name for isComplete() check.
        $this->setJobName($job, $responseBody);
      }
    }
  }

}
