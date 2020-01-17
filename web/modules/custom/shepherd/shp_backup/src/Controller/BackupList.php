<?php

namespace Drupal\shp_backup\Controller;

use Drupal\node\NodeInterface;
use UniversityOfAdelaide\OpenShift\Objects\Backups\Phase;

/**
 * Controller for listing backups.
 */
class BackupList extends ListControllerBase {

  /**
   * Builds backup list page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The site node.
   *
   * @return array
   *   Render array
   */
  public function list(NodeInterface $node) {
    $table = [
      '#theme' => 'table',
      '#header' => [
        $this->t('Name'),
        $this->t('Environment'),
        $this->t('Phase'),
        $this->t('Started'),
        $this->t('Completed'),
      ],
      '#rows' => [],
      '#empty' => $this->t('No backups for this site yet.'),
    ];
    /** @var \UniversityOfAdelaide\OpenShift\Objects\Backups\BackupList $backup_list */
    if (!$backup_list = $this->orchestrationProvider->getBackupsForSite($node->id())) {
      return $table;
    }
    foreach ($backup_list->getBackupsByCreatedTime() as $backup) {
      $environment = $this->nodeStorage->load($backup->getLabel('environment'));
      $table['#rows'][] = [
        $this->backupService->getFriendlyName($backup),
        $environment ? $this->environmentService->getEnvironmentLink($environment, FALSE)->toString() : $this->t('Deleted'),
        Phase::getFriendlyPhase($backup->getPhase()),
        $backup->getStartTimestamp() ? $this->formatDate($backup->getStartTimestamp()) : $this->t('N/A'),
        $backup->getCompletionTimestamp() ? $this->formatDate($backup->getCompletionTimestamp()) : $this->t('N/A'),
      ];
    }
    return $table;
  }

}
