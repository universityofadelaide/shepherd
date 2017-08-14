<?php

namespace Drupal\shp_orchestration\Service;

use Drupal\Core\State\State;

/**
 * Class ActiveJobManager.
 */
class ActiveJobManager {

  const STATE_KEY_PREFIX = 'shp_job.';

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
    $this->state->set(static::STATE_KEY_PREFIX . $job->entityId, $job);
  }

  /**
   * Remove the active job by entity id.
   *
   * @param int $entityId
   *   The entity id.
   */
  public function remove(int $entityId) {
    $this->state->delete(static::STATE_KEY_PREFIX . $entityId);
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
    $stateIds = $this->applyIdPrefix($entityIds);
    return $this->state->getMultiple($stateIds);
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
    if ($job = $this->get([static::STATE_KEY_PREFIX . $entityId])) {
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
    foreach ($this->applyIdPrefix($entityIds) as $entityId) {
      if ($this->isComplete($entityId)) {
        $this->remove($entityId);
      }
    }
  }

  /**
   * Apply prefix to multiple keys.
   *
   * @param array $entityIds
   *   An array of entity ids.
   *
   * @return array
   *   An array of prefixed state api keys.
   */
  protected function applyIdPrefix(array $entityIds) {
    return array_map(
      function ($entityId) {
        return static::STATE_KEY_PREFIX . $entityId;
      },
      $entityIds);
  }

}
