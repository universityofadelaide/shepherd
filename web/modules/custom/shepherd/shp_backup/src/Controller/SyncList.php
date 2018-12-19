<?php

namespace Drupal\shp_backup\Controller;

use Drupal\Core\Link;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for listing syncs.
 */
class SyncList extends ListControllerBase {

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
        $this->t('From environment'),
        $this->t('To environment'),
        $this->t('Phase'),
        $this->t('Created'),
      ],
      '#rows' => [],
      '#empty' => $this->t('No syncs for this site yet.'),
    ];
    /** @var \UniversityOfAdelaide\OpenShift\Objects\Backups\SyncList $sync_list */
    if (!$sync_list = $this->orchestrationProvider->getSyncsForSite($node->id())) {
      return $table;
    }
    foreach ($sync_list->getSyncsByCreatedTime() as $sync) {
      $from = $this->nodeStorage->load($sync->getLabel('environment_id_from'));
      // Protect against environments that have been deleted.
      if (!$from) {
        continue;
      }
      $to = $this->nodeStorage->load($sync->getLabel('environment_id_to'));
      if (!$to) {
        continue;
      }
      $table['#rows'][] = [
        $this->environmentService->getEnvironmentLink($from, FALSE)->toString(),
        $this->environmentService->getEnvironmentLink($to, FALSE)->toString(),
        $sync->getPhase(),
        $this->formatDate($sync->getCreationTimestamp()),
      ];
    }
    return $table;
  }

}
