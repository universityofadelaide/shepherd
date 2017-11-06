<?php

namespace Drupal\shp_custom\Controller;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

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
    $form_state->setRedirectUrl(Url::fromRoute('view.shp_site_environments.page_1', [
      'node' => $site_id,
    ]));
  }

}
