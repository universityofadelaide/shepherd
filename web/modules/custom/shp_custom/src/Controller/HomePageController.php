<?php

namespace Drupal\shp_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for homepage requests.
 */
class HomePageController extends ControllerBase {

  /**
   * Processes homepage requests based on authentication and permissions.
   *
   * @return array|RedirectResponse
   *   A render array or redirect response.
   */
  public function index() {
    $user = \Drupal::currentUser();

    if ($user->isAnonymous()) {
      $base_url_path = trim(Url::fromUri('base:', ['absolute' => TRUE])->toString(), '/');
      $login_url = $base_url_path . '/caslogin';
      $response = [
        '#type' => 'markup',
        '#markup' => t('You need to @login to use Shepherd.', ['@login' => Link::fromTextAndUrl('login', Url::fromUri($login_url))->toString()]),
      ];
    }
    elseif ($user->hasPermission('shp view sites')) {
      $response = new RedirectResponse('/websites');
    }
    else {
      $response = [
        '#type' => 'markup',
        '#markup' => t("You don't have permission to use Shepherd."),
      ];
    }
    return $response;
  }

}
