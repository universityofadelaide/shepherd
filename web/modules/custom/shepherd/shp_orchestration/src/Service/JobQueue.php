<?php

namespace Drupal\shp_orchestration\Service;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\shp_orchestration\Exception\JobInProgressException;

/**
 * Class JobQueue.
 */
class JobQueue {

  const SHP_ORCHESTRATION_JOB_QUEUE = 'shp_orchestration_job_queue';

  protected $queueFactory;

  /**
   * Queue manager service.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected $queueManager;

  /**
   * Active job manager service.
   *
   * @var \Drupal\shp_orchestration\Service\ActiveJobManager
   */
  protected $activeJobManager;

  /**
   * JobQueue constructor.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   Queue service.
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queueManager
   *   Queue worker service.
   * @param \Drupal\shp_orchestration\Service\ActiveJobManager $activeJobManager
   *   Active job manager service.
   */
  public function __construct(QueueFactory $queueFactory, QueueWorkerManagerInterface $queueManager, ActiveJobManager $activeJobManager) {
    $this->queueFactory = $queueFactory;
    $this->queueManager = $queueManager;
    $this->activeJobManager = $activeJobManager;
  }

  /**
   * Processes x number of jobs in the job queue.
   *
   * @param int $numJobs
   *   Number of queue items to process.
   */
  public function process(int $numJobs = 20) {
    $this->queueFactory->get(static::SHP_ORCHESTRATION_JOB_QUEUE)->createQueue();
    $queue = $this->queueFactory->get(static::SHP_ORCHESTRATION_JOB_QUEUE);

    // @todo while claimItem() and count?
    for ($count = 0; $count < $numJobs; $count++) {
      if (!$job = $queue->claimItem()) {
        return;
      }
      $queue_worker = $this->queueManager->createInstance($job->data->queueWorker);
      try {
        // Check if we can free up processing for this environment.
        $this->activeJobManager->updateState([$job->data->entityId]);
        // Set this job "in progress".
        $this->activeJobManager->add($job->data);
        // Process it...
        $queue_worker->processItem($job->data);
        // Remove it from the queue.
        $queue->deleteItem($job);
      }
      catch (SuspendQueueException $e) {
        $queue->releaseItem($job);
        break;
      }
      catch (JobInProgressException $e) {
        // Job already in progress, return job to the queue.
        $queue->releaseItem($job);
        break;
      }
      catch (\Exception $e) {
        watchdog_exception('shp_orchestration', $e);
      }
    }
  }

  /**
   * Add a job to the queue for processing.
   *
   * @param int $environmentId
   *   The id of the environment the job will act on.
   * @param string $queueWorker
   *   The queue worker to perform processing.
   * @param string $completeService
   *   The service to query for job complete status.
   * @param array $data
   *   Extra variables a worker may require.
   *
   * @return string
   *   The unique ID for the queue item.
   */
  public function add(int $environmentId, $queueWorker, $completeService, array $data = []) {
    $job = (object) array_merge($data, [
      'entityId' => $environmentId,
      'queueWorker' => $queueWorker,
      'completeService' => $completeService,
    ]);
    $queue = $this->queueFactory->get(static::SHP_ORCHESTRATION_JOB_QUEUE);
    return $queue->createItem($job);
  }

}
