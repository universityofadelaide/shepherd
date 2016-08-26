<?php
/**
 * @file
 * Definition of Drupal\ua_sm_custom\Plugin\views\field\SiteEnvironmentOperations.
 */

namespace Drupal\ua_sm_custom\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\Core\Url;
use Drupal\views\ResultRow;

/**
 * Field handler to perform operations on site environments.
 *
 * @package Drupal\ua_sm_custom\Plugin\views\field
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("site_environment_operations")
 */
class SiteEnvironmentOperations extends FieldPluginBase {

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
    $site = $entity->field_ua_sm_site->getValue()[0]['target_id'];
    $clone_url = Url::fromRoute('ua_sm_custom.environment-clone-form', ['site' => $site, 'environment' => $environment]);
    $backup_url = Url::fromRoute('ua_sm_custom.environment-backup-form', ['site' => $site, 'environment' => $environment]);
    $restore_url = Url::fromRoute('ua_sm_custom.environment-restore-form', ['site' => $site, 'environment' => $environment]);

    $build['clone_environment'] = [
      '#type' => 'link',
      '#title' => $this->t('Clone'),
      '#url' => $clone_url,
      '#options' => [
        'attributes' => [
          'class' => [
            'button',
            'c-btn',
          ],
        ],
      ],
    ];

    $build['backup_environment'] = [
      '#type' => 'link',
      '#title' => $this->t('Backup'),
      '#url' => $backup_url,
      '#options' => [
        'attributes' => [
          'class' => [
            'button',
            'c-btn',
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
          ],
        ],
      ],
    ];

    return $build;
  }

}
