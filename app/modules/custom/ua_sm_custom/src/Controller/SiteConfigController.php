<?php

/**
 * @file
 * Contains Drupal\ua_site_config_export\Controller\SiteConfigExportController.
 */

namespace Drupal\ua_sm_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


/**
 * Returns responses for config module routes.
 */
class SiteConfigController extends ControllerBase {

  /**
   * Handles a site instance config json request.
   *
   * @param int $nid
   *   Site instance node ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Site instance config as JSON.
   */
  public function siteInstanceConfig($nid) {
    $config = \Drupal::service('ua_sm_custom.site_instance_config')
      ->generate($nid);
    if ($config) {
      return new JsonResponse($config);
    }
    else {
      throw new NotFoundHttpException();
    }
  }

  /**
   * Handles varnish config json request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Varnish config as JSON.
   */
  public function varnishConfig() {
    $config = \Drupal::service('ua_sm_custom.varnish_config')->generate();
    return new JsonResponse($config);
  }

}
