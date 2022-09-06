<?php

namespace Drupal\shp_backup\Controller;

use UniversityOfAdelaide\OpenShift\Objects\Backups\Phase;

/**
 * Controller for listing syncs.
 *
 * @removed Unless we can find a way to view across all the namespaces.
 */
class SyncGlobalList extends ListControllerBase {

  /**
   * Builds sync list page.
   *
   * @return array
   *   Render array
   */
  public function list() {
    $table = [
      '#theme' => 'table',
      '#header' => [
        $this->t('From Environment'),
        $this->t('To Environment'),
        $this->t('Backup Phase'),
        $this->t('Restore Phase'),
        $this->t('Started'),
        $this->t('Completed'),
      ],
      '#rows' => [],
      '#empty' => $this->t('No syncs yet.'),
    ];
    /** @var \UniversityOfAdelaide\OpenShift\Objects\Backups\SyncList $sync_list */
    if (!$sync_list = $this->orchestrationProvider->getSyncs()) {
      return $table;
    }
    foreach ($sync_list->getSyncsByCreatedTime() as $sync) {
      $fromEnvironment = $this->nodeStorage->load($sync->getBackupEnv());
      $toEnvironment = $this->nodeStorage->load($sync->getRestoreEnv());
      // Protect against environments that have been deleted.
      if (!$fromEnvironment || !$toEnvironment) {
        continue;
      }
      $table['#rows'][] = [
        $this->environmentService->getEnvironmentLink($fromEnvironment, FALSE)->toString(),
        $this->environmentService->getEnvironmentLink($toEnvironment, FALSE)->toString(),
        Phase::getFriendlyPhase($sync->getBackupPhase()),
        Phase::getFriendlyPhase($sync->getRestorePhase()),
        $sync->getStartTimestamp() ? $this->formatDate($sync->getStartTimestamp()) : $this->t('N/A'),
        $sync->getCompletionTimestamp() ? $this->formatDate($sync->getCompletionTimestamp()) : $this->t('N/A'),
      ];
    }
    return $table;
  }

}
