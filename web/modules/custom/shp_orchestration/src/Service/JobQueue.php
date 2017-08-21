<?php

namespace Drupal\shp_orchestration\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\shp_orchestration\Exception\JobInProgressException;

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
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queueManager
   * @param \Drupal\shp_orchestration\Service\ActiveJobManager $activeJobManager
   *
   * @internal param \Drupal\Core\Queue\QueueFactory $queueFactory
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
    for ($count = 0; $count < $numJobs; $count++) {
      if (!$job = $queue->claimItem()) {
        return;
      }
      $queue_worker = $this->queueManager->createInstance($job->data->queueWorker);

      try {
        $this->activeJobManager->add($job->data);
        $queue_worker->processItem($job->data);
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
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to operate on.
   * @param string $queueWorker
   *   The queue worker to perform processing.
   * @param array $data
   *   Extra variables a worker may require.
   *
   * @return string
   *   The unique ID for the queue item.
   */
  public function add(EntityInterface $entity, $queueWorker, array $data = []) {
    $job = (object) array_merge($data, [
      'entityId' => $entity->id(),
      'queueWorker' => $queueWorker,
    ]);
    $queue = $this->queueFactory->get(static::SHP_ORCHESTRATION_JOB_QUEUE);
    return $queue->createItem($job);
  }

}
