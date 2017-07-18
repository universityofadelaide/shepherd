<?php

namespace Drupal\shp_backup\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\Core\Url;
use Drupal\views\ResultRow;

/**
 * Field handler to perform operations on site environments.
 *
 * @package Drupal\shp_backup\Plugin\views\field
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("shp_backup_operations")
 */
class ShpBackupOperations extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $values->_entity;
    // For each row.
    $environment = $entity->id();
    $site = $entity->field_shp_site->target_id;
    $backup_url = Url::fromRoute('shp_backup.environment-backup-form', ['site' => $site, 'environment' => $environment]);
    $restore_url = Url::fromRoute('shp_backup.environment-restore-form', ['site' => $site, 'environment' => $environment]);

    $build['backup_environment'] = [
      '#type' => 'link',
      '#title' => $this->t('Backup'),
      '#url' => $backup_url,
      '#options' => [
        'attributes' => [
          'class' => [
            'button',
            'c-btn',
            'c-btn--small',
          ],
        ],
      ],
    ];

    $build['restore_environment'] = [
      '#type' => 'link',
      '#title' => $this->t('Restore'),
      '#url' => $restore_url,
      '#options' => [
        'attributes' => [
          'class' => [
            'button',
            'c-btn',
            'c-btn--small',
          ],
        ],
      ],
    ];

    return $build;
  }

}
