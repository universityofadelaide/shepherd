<?php

namespace Drupal\shp_orchestration\Service;

use Drupal\Core\Queue\QueueFactory;

class JobQueue {

  const SHP_ORCHESTRATION_JOB_QUEUE = 'shp_orchestration_job_queue';

  /**
   * Queue service.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * JobQueue constructor.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   */
  public function __construct(QueueFactory $queueFactory) {
    $this->queue = $queueFactory->get(static::SHP_ORCHESTRATION_JOB_QUEUE, TRUE);
    $this->queue->createQueue();
  }

  /**
   * Processes x number of items in the job queue.
   *
   * @param int $numItems
   */
  public function process(int $numItems = 20) {

  }

  /**
   * Add a job to the queue for processing.
   *
   * @param array $job
   */
  public function add(array $job) {
    // Do more stuff?
    $this->queue->createItem($job);
  }

}
