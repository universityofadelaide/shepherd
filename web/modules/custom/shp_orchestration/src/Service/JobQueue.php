<?php

namespace Drupal\shp_orchestration\Service;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\SuspendQueueException;

class JobQueue {

  const SHP_ORCHESTRATION_JOB_QUEUE = 'shp_orchestration_job_queue';

  /**
   * Queue service.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * Queue manager service.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  private $queueManager;

  /**
   * JobQueue constructor.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queueManager
   */
  public function __construct(QueueFactory $queueFactory, QueueWorkerManagerInterface $queueManager) {
    $this->queue = $queueFactory->get(static::SHP_ORCHESTRATION_JOB_QUEUE, TRUE);
    $this->queue->createQueue();
    $this->queueManager = $queueManager;
  }

  /**
   * Processes x number of jobs in the job queue.
   *
   * @param int $numJobs
   *   Number of queue items to process.
   */
  public function process(int $numJobs = 20) {
    for ($count = 0; $count < $numJobs; $count++) {
      $job = $this->queue->claimItem();
      try {
        /** @var \Drupal\Core\Queue\QueueWorkerInterface $queue_worker */
        $queue_worker = $this->queueManager->createInstance($job->worker);
        // @todo ActiveJobMananger->add($job); ?
        $queue_worker->processItem($job->data);
        $this->queue->deleteItem($job);
      }
      catch (SuspendQueueException $e) {
        $this->queue->releaseItem($job);
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
   * @param \stdClass $job
   *   The queue item.
   */
  public function add(\stdClass $job) {
    $this->queue->createItem($job);
  }

}
