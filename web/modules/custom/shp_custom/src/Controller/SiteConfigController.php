<?php

/**
 * @file
 * Contains Drupal\shp_custom\Controller\SiteConfigExportController.
 */

namespace Drupal\shp_custom\Controller;

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
    $config = \Drupal::service('shp_custom.site_instance_config')
      ->generate($nid);
    if ($config) {
      return new JsonResponse($config);
    }
    else {
      throw new NotFoundHttpException();
    }
  }

  /**
   * Handles hosts config json request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Hosts config as JSON.
   */
  public function hostsConfig() {
    $config = \Drupal::service('shp_custom.hosts_config')->generate();
    return new JsonResponse($config);
  }

}
