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
    /** @var \UniversityOfAdelaide\OpenShift\Objects\Backups\BackupList $backup_list */
    $backup_list = $this->orchestrationProvider->getBackupsForSite($node->id());
    $rows = [];
    foreach ($backup_list->getBackups() as $backup) {
      $environment = $this->nodeStorage->load($backup->getLabel('environment_id'));
      // Protect against environments that have been deleted.
      if (!$environment) {
        continue;
      }
      $rows[] = [
        $backup->getName(),
        $environment->toLink(),
        $backup->getPhase(),
        // These values aren't available until the backup has finished.
        $backup->isCompleted() ? $this->formatDate($backup->getStartTimestamp()) : $this->t('N/A'),
        $backup->isCompleted() ? $this->formatDate($backup->getCompletionTimestamp()) : $this->t('N/A'),
        $this->dateFormatter->formatInterval($this->parseDate($backup->getExpires())->getTimestamp() - $this->time->getRequestTime())
      ];
    }
    return [
      '#theme' => 'table',
      '#header' => [
        $this->t('Name'),
        $this->t('Environment'),
        $this->t('Phase'),
        $this->t('Started'),
        $this->t('Completed'),
        $this->t('Expires'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No backups for this site yet.'),
    ];
  }

}
