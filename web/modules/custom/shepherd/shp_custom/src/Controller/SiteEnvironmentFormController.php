<?php

namespace Drupal\shp_custom\Controller;

use Drupal\Core\Form\FormStateInterface;

/**
 * Class SiteEnvironmentFormController
 */
class SiteEnvironmentFormController {

  /**
   * Alters a forms state to redirect back the site environments view.
   *
   * @param array $form
   *   Form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   */
  public static function setRedirect(array $form, FormStateInterface $form_state) {
    $site_id = \Drupal::routeMatch()->getRawParameters()->get('site_id');
    $form_state->setRedirect('view.shp_site_environments.page_1', [
      'node' => $site_id,
    ]);
  }

}
