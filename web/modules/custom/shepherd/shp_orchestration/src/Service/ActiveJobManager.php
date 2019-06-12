<?php

namespace Drupal\shp_orchestration\Service;

use Drupal\Core\State\StateInterface;
use Drupal\shp_orchestration\Exception\JobInProgressException;

/**
 * Class ActiveJobManager.
 */
class ActiveJobManager {

  const STATE_KEY_PREFIX = 'shp_job.';

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * ActiveJobManager constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * Record a job in progress.
   *
   * @param object $job
   *   The job.
   *
   * @throws \Drupal\shp_orchestration\Exception\JobInProgressException
   *   There's already a job in progress for the given environment.
   */
  public function add(\stdClass $job) {
    if ($this->state->get(static::STATE_KEY_PREFIX . $job->entityId)) {
      throw new JobInProgressException('A job is already in progress for this environment.');
    }
    $this->update($job);
  }

  /**
   * Update a job in progress.
   *
   * Should only be used to add data to an existing job.
   * Don't be evil! Use add() for new jobs.
   *
   * @param object $job
   *   The job.
   */
  public function update(\stdClass $job) {
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
    $stateIds = $this->applyKeyPrefix($entityIds);
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
    $jobs = $this->get([$entityId]);
    $job = reset($jobs);
    if ($job) {
      // @todo Fix this service call with a better pattern. Plugins?
      return \Drupal::service($job->completeService)->isComplete($job);
    }
    // There is no active job for this environment.
    return TRUE;
  }

  /**
   * Update the state of all jobs in progress.
   *
   * @param array $entityIds
   *   The list of job id's to check.
   */
  public function updateState(array $entityIds = []) {
    foreach ($entityIds as $entityId) {
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
  protected function applyKeyPrefix(array $entityIds) {
    return array_map(
      function ($entityId) {
        return static::STATE_KEY_PREFIX . $entityId;
      },
      $entityIds);
  }

}
