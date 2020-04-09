<?php

namespace Drupal\shp_backup\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
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
        $this->t('Type'),
        $this->t('Environment'),
        $this->t('Phase'),
        $this->t('Started'),
        $this->t('Completed'),
        $this->t('Operations'),
      ],
      '#rows' => [],
      '#empty' => $this->t('No backups for this site yet.'),
    ];
    /** @var \UniversityOfAdelaide\OpenShift\Objects\Backups\BackupList $backup_list */
    if (!$backup_list = $this->orchestrationProvider->getBackupsForSite($node->id())) {
      return $table;
    }
    $modal_attributes = [
      'class' => ['use-ajax'],
      'data-dialog-type' => 'modal',
      'data-dialog-options' => Json::encode([
        'width' => '50%',
        'height' => '50%',
      ]),
    ];
    foreach ($backup_list->getBackupsByCreatedTime() as $backup) {
      // Backups that have been deleted may still be getting finalized. In that
      // case, they will have a deletionTimestamp set, so hide them from this
      // list.
      if ($backup->getDeletionTimestamp()) {
        continue;
      }
      $environment = $this->nodeStorage->load($backup->getLabel('environment'));
      $operations = [
        '#type' => 'dropbutton',
        '#links' => [
          'edit' => [
            'title' => $this->t('Edit'),
            'url' => Url::fromRoute('shp_backup.backup-edit-form', [
              'site' => $node->id(),
              'backupName' => $backup->getName(),
            ]),
            'attributes' => $modal_attributes,
          ],
          'delete' => [
            'title' => $this->t('Delete'),
            'url' => Url::fromRoute('shp_backup.backup-delete-form', [
              'site' => $node->id(),
              'backupName' => $backup->getName(),
            ]),
            'attributes' => $modal_attributes,
          ],
        ],
      ];
      $table['#rows'][] = [
        $backup->getFriendlyName(),
        $backup->isManual() ? $this->t('Manual') : $this->t('Scheduled'),
        $environment ? $this->environmentService->getEnvironmentLink($environment, FALSE)->toString() : $this->t('Deleted'),
        Phase::getFriendlyPhase($backup->getPhase()),
        $backup->getStartTimestamp() ? $this->formatDate($backup->getStartTimestamp()) : $this->t('N/A'),
        $backup->getCompletionTimestamp() ? $this->formatDate($backup->getCompletionTimestamp()) : $this->t('N/A'),
        ['data' => $operations],
      ];
    }
    return $table;
  }

}
