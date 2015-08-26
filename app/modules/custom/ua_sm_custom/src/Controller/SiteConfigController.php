<?php

/**
 * @file
 * Contains Drupal\ua_site_config_export\Controller\SiteConfigExportController.
 */

namespace Drupal\ua_sm_custom\Controller;

use Drupal\Component\Serialization\Yaml;
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
   * Download json representation of a site instance.
   *
   * @param int $nid
   *   Site NID.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Json response.
   */
  public function siteInstance($nid) {
    $site_instance = $this->getSiteInstanceArray($nid);
    if ($site_instance) {
      return new JsonResponse($site_instance);
    }
    else {
      throw new NotFoundHttpException();
    }
  }

  /**
   * Json representation of a site instance, including site and dist details.
   *
   * @param int $nid
   *   Site ID.
   *
   * @return array
   *   Site instance array.
   */
  private function getSiteInstanceArray($nid) {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'ua_sm_site_instance')
      ->condition('nid', $nid);
    $result = $query->execute();
    if ($result) {
      $site_instance = \Drupal::entityManager()->getStorage('node')->load($nid);
      $site = reset($site_instance->field_ua_sm_site->referencedEntities());
      $distribution = reset($site->field_ua_sm_distribution->referencedEntities());

      return [
        'site_instance' => $site_instance->toArray(),
        'site' => $site ? $site->toArray() : NULL,
        'distribution' => $distribution ? $distribution->toArray() : NULL,
      ];
    }
    else {
      return [];
    }
  }

}
