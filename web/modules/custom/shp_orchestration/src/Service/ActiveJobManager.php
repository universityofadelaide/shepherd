<?php

namespace Drupal\shp_orchestration\Service;

use Drupal\Core\State\State;

class ActiveJobManager {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * ActiveJobManager constructor.
   *
   * @param \Drupal\Core\State\State $state
   *   The state service.
   */
  public function __construct(State $state) {
    $this->state = $state;
  }

  /**
   * Record a job in progress.
   *
   * @param \stdClass $job
   *   The job.
   */
  public function add(\stdClass $job) {
    $this->state->set($job->id, $job);
  }

  /**
   * Remove the active job by entity id.
   *
   * @param int $entityId
   *   The entity id.
   */
  public function remove(int $entityId) {
    $this->state->delete($entityId);
  }

  /**
   * Get the job from the active jobs.
   *
   * @param array $entityIds
   *   Entity ids to retrieve.
   *
   * @return array
   *   An array of jobs.
   */
  public function get(array $entityIds) {
    return $this->state->getMultiple($entityIds);
  }

  /**
   * Check if a job has completed.
   *
   * @param int $entityId
   *   The entity id.
   *
   * @return bool
   *   Has the job completed?
   */
  public function isComplete(int $entityId) {
    // @todo implement timeout or just manually clear broken state?
    if ($job = $this->get([$entityId])) {
      return \Drupal::service($job->entityType)->isComplete($job->jobId, $entityId);
    }
    return TRUE;
  }

  /**
   * Update the state of all jobs in progress.
   *
   * @param array $entityIds
   *   The list of job id's to check.
   */
  public function update(array $entityIds = []) {
    foreach ($entityIds as $entityId) {
      if ($this->isComplete($entityId)) {
        $this->remove($entityId);
      }
    }
  }

}
