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
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   */
  public static function setRedirect(FormStateInterface $form_state) {
    $site_id = \Drupal::routeMatch()->getRawParameters()->get('site_id');
    $route_name = \Drupal::routeMatch()->getRouteName();
    $form_state->setRedirect($route_name, [
      'site_id' => $site_id,
    ]);
  }

}
