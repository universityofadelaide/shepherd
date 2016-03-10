<?php

/**
 * @file
 * Contains Drupal\ua_site_config_export\Controller\HomePageController.
 */

namespace Drupal\ua_sm_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for homepage requests.
 */
class HomePageController extends ControllerBase {

  /**
   * Processes incoming homepage requests and generates appropriate responses based on authentication and permissions.
   *
   * @return mixed array|RedirectResponse
   */
  public function index() {
    $user = \Drupal::currentUser();

    if ($user->isAnonymous()) {
      $base_url_path = trim(Url::fromUri('base:', ['absolute' => TRUE])->toString(), '/');
      $login_url = $base_url_path . '/caslogin';
      $response = [
        '#type' => 'markup',
        '#markup' => t('You need to <a href="' . $login_url . '">login</a> to use site manager.'),
      ];
    }
    elseif ($user->hasPermission('sm view sites')) {
      $response = new RedirectResponse('/websites');
    }
    else {
      $response = [
        '#type' => 'markup',
        '#markup' => t('You don\'t have permission to use site manager.'),
      ];
    }
    return $response;
  }

}
