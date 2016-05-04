<?php
/**
 * @file
 * Definition of Drupal\ua_sm_custom\Plugin\views\field\SiteEnvironmentsOperations.
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
 * @ViewsField("site_environments_operations")
 */
class SiteEnvironmentsOperations extends FieldPluginBase {

  /**
   * @{inheritdoc}
   */
  public function query() {
  }

  /**
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $values->_entity;
    // For each row.
    $environment = $entity->id();
    $site = $entity->field_ua_sm_site->getValue()[0]['target_id'];
    $clone_url = Url::fromRoute('ua_sm_custom.environment-clone-form', ['site' => $site, 'environment' => $environment]);

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

    return $build;
  }
}
