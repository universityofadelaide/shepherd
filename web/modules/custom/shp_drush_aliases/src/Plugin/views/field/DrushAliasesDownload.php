<?php

namespace Drupal\shp_drush_aliases\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Url;

/**
 * Field handler to add the drush aliases download button.
 *
 * @package Drupal\shp_drush_aliases\Plugin\views\field
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("drush_aliases_download")
 */
class DrushAliasesDownload extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $url = Url::fromRoute('shp_drush_aliases.drush_aliases', ['nid' => $values->_entity->id()]);
    // @todo - add a label for this.
    $build['drush_aliases_button'] = [
      '#type' => 'link',
      '#title' => 'Download Drush Aliases',
      '#url' => $url,
      '#options' => [
        'attributes' => [
          'class' => [
            'button',
            'c-btn',
          ],
          'style' => [
            'margin-bottom:0px'
          ],
        ],
      ],
    ];

    return $build;
  }

}

