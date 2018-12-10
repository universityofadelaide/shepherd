<?php

namespace Drupal\shp_backup\Controller;

use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for listing backups.
 */
class BackupList extends ListControllerBase {

  /**
   * Builds backup list page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Current request.
   * @param \Drupal\node\NodeInterface $node
   *   The site node.
   *
   * @return array
   *   Render array
   */
  public function list(Request $request, NodeInterface $node) {
    $table = [
      '#theme' => 'table',
      '#header' => [
        $this->t('Name'),
        $this->t('Environment'),
        $this->t('Phase'),
        $this->t('Started'),
        $this->t('Completed'),
        $this->t('Expires'),
      ],
      '#rows' => [],
      '#empty' => $this->t('No backups for this site yet.'),
    ];
    /** @var \UniversityOfAdelaide\OpenShift\Objects\Backups\BackupList $backup_list */
    if (!$backup_list = $this->orchestrationProvider->getBackupsForSite($node->id())) {
      return $table;
    }
    foreach ($backup_list->getBackupsByStartTime() as $backup) {
      $environment = $this->nodeStorage->load($backup->getLabel('environment_id'));
      // Protect against environments that have been deleted.
      if (!$environment) {
        continue;
      }
      $table['#rows'][] = [
        $this->backupService->getFriendlyName($backup),
        $this->environmentService->getEnvironmentLink($environment, FALSE)->toString(),
        $backup->getPhase(),
        // These values aren't available until the backup has finished.
        $backup->isCompleted() ? $this->formatDate($backup->getStartTimestamp()) : $this->t('N/A'),
        $backup->isCompleted() ? $this->formatDate($backup->getCompletionTimestamp()) : $this->t('N/A'),
        $backup->getExpires() ? $this->dateFormatter->formatInterval($this->parseDate($backup->getExpires())->getTimestamp() - $this->time->getRequestTime()) : $this->t('N/A')
      ];
    }
    return $table;
  }

}
