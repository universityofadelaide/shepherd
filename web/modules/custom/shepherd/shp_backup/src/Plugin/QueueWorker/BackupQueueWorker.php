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
    /** @var $backup \Drupal\node\NodeInterface */
    if ($backup = $this->nodeStorage->load($job->backupId)) {
      // Perform the job.
      if ($responseBody = $this->backup->create($backup)) {
        // Update the job name for isComplete() check.
        $this->setJobName($job, $responseBody);
        return;
      }
    }

    // If we get to here, something is going wrong, alert about the backup that is an issue.
    \Drupal::logger('shp_backup')->error('An error occurred processing: %backup', ['%backup' => $job->backupId]);
  }

}
