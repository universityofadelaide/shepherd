<?php

namespace Drupal\shp_backup\Controller;

use Drupal\Core\Link;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;
use UniversityOfAdelaide\OpenShift\Objects\Backups\Phase;

/**
 * Controller for listing restores.
 */
class RestoreList extends ListControllerBase {

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
        $this->t('From backup'),
        $this->t('Environment'),
        $this->t('Phase'),
        $this->t('Created'),
      ],
      '#rows' => [],
      '#empty' => $this->t('No restores for this site yet.'),
    ];
    /** @var \UniversityOfAdelaide\OpenShift\Objects\Backups\RestoreList $restore_list */
    if (!$restore_list = $this->orchestrationProvider->getRestoresForSite($node->id())) {
      return $table;
    }
    foreach ($restore_list->getRestoresByCreatedTime() as $restore) {
      $environment = $this->nodeStorage->load($restore->getLabel('environment_id'));
      // Protect against environments that have been deleted.
      if (!$environment) {
        continue;
      }
      $backup = $this->orchestrationProvider->getBackup($restore->getBackupName());
      $table['#rows'][] = [
        $backup ? $this->backupService->getFriendlyName($backup) : '',
        $this->environmentService->getEnvironmentLink($environment, FALSE)->toString(),
        Phase::getFriendlyPhase($restore->getPhase()),
        $this->formatDate($restore->getCreationTimestamp()),
      ];
    }
    return $table;
  }

}
