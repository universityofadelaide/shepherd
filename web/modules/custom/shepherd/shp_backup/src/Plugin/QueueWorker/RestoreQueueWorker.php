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
    /** @var $backup \Drupal\node\NodeInterface */
    /** @var $environment \Drupal\node\NodeInterface */
    if (($backup = $this->nodeStorage->load($job->backupId)) &&
      ($environment = $this->nodeStorage->load($job->environmentId))) {
      // Perform the job.
      if ($responseBody = $this->backup->restore($backup, $environment)) {
        // Update the job name for isComplete() check.
        $this->setJobName($job, $responseBody);
        return;
      }
    }

    // If we get to here, something is going wrong, alert about the restore that is an issue.
    \Drupal::logger('shp_backup')->error('An error occurred processing: %restore for %environment',
      ['%restore' => $job->backupId, '%environment' => $job->environmentId]);
  }

}
