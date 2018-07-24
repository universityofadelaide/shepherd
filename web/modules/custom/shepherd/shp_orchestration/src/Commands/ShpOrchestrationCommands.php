<?php

namespace Drupal\shp_orchestration\Commands;

use Drupal\shp_orchestration\Service\JobQueue;
use Drush\Commands\DrushCommands;

/**
 * Shepherd Orchestration Drush commandfile.
 */
class ShpOrchestrationCommands extends DrushCommands {

  /**
   * The job queue.
   *
   * @var \Drupal\shp_orchestration\Service\JobQueue
   */
  protected $jobQueue;

  /**
   * ShpOrchestrationCommands constructor.
   *
   * @param \Drupal\shp_orchestration\Service\JobQueue $jobQueue
   *   The job queue.
   */
  public function __construct(JobQueue $jobQueue) {
    parent::__construct();
    $this->jobQueue = $jobQueue;
  }

  /**
   * Process any jobs in the job queue.
   *
   * @usage drush shepherd-process-job-queue
   *   Process any jobs in the job queue
   * @usage drush shp-p
   *   Process any jobs in the job queue
   *
   * @command shepherd:process-job-queue
   * @aliases shp-p,shepherd-process-job-queue
   */
  public function processJobQueue() {
    $this->jobQueue->process();
    $this->output->writeln('Job queue processed.');
  }

}
