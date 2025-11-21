<?php

namespace Drupal\shp_orchestration\Drush\Commands;

use Drush\Commands\DrushCommands;

/**
 * Drush commands for Shepherd Orchestration.
 */
class OrchestrationCommands extends DrushCommands {

  /**
   * Process any jobs in the job queue.
   *
   * This command previously lived in hook_drush_command() which is removed in
   * Drush 9+. It is intentionally a no-op here because the functionality was
   * removed. The command is kept to avoid breaking existing automations.
   *
   * @command shepherd-process-job-queue
   * @aliases shp-p
   */
  public function processJobQueue(): int {
    $this->logger()->notice('shepherd-process-job-queue is deprecated and currently performs no actions.');
    return self::EXIT_SUCCESS;
  }

}
