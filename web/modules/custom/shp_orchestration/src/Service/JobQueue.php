<?php

namespace Drupal\shp_orchestration\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Queue\QueueDatabaseFactory;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\shp_orchestration\Exception\JobInProgressException;

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
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queueManager
   * @param \Drupal\shp_orchestration\Service\ActiveJobManager $activeJobManager
   */
  public function __construct(QueueDatabaseFactory $queueManager, ActiveJobManager $activeJobManager) {
    $this->queue = $queueManager->get(static::SHP_ORCHESTRATION_JOB_QUEUE, TRUE);
    $this->queue->createQueue();
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
    for ($count = 0; $count < $numJobs; $count++) {
      if (!$job = $this->queue->claimItem()) {
        return;
      }
      try {
        /** @var \Drupal\Core\Queue\QueueWorkerInterface $queue_worker */
        $queue_worker = $this->queueManager->createInstance($job->queueWorker);
        $this->activeJobManager->add($job);
        $queue_worker->processItem($job);
        $this->queue->deleteItem($job);
      }
      catch (SuspendQueueException $e) {
        $this->queue->releaseItem($job);
        break;
      }
      catch (JobInProgressException $e) {
        // Job already in progress, return job to the queue.
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
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to operate on.
   * @param string $queueWorker
   *   The queue worker to perform processing.
   */
  public function add(EntityInterface $entity, $queueWorker) {
    $job = (object) [
      'entityId' => $entity->id(),
      'queueWorker' => $queueWorker,
    ];
    $this->queue->createItem($job);
  }

}
