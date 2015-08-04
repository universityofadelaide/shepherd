<?php

/**
 * @file
 * Contains Drupal\ua_site_config_export\Controller\SiteConfigExportController.
 */

namespace Drupal\ua_site_config_export\Controller;

use Drupal\Component\Serialization\Yaml;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


/**
 * Returns responses for config module routes.
 */
class SiteConfigExportController {

  /**
   * Download yaml config for a site.
   *
   * @param int $nid
   *   Site NID.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Yaml file.
   */
  public function download($nid) {
    $yaml = $this->getSiteConfigYaml($nid);
    if ($yaml) {
      $response = new Response($yaml);
      $d = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'config.yaml');
      $response->headers->set('Content-Disposition', $d);
      return $response;
    }
    else {
      throw new NotFoundHttpException();
    }
  }

  /**
   * Config for a site.
   *
   * @param int $nid
   *   Site ID.
   *
   * @return string
   *   Returns yaml config.
   */
  private function getSiteConfigYaml($nid) {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'ua_sm_site')
      ->condition('nid', $nid);
    $result = $query->execute();
    if ($result) {
      $node = \Drupal::entityManager()->getStorage('node')->load($nid);
      $config = [
        'drupal_config' => [
          'system.site' => [
            'site_id' => $node->field_ua_sm_site_id->value,
            'name' => $node->field_ua_sm_site_title->value,
          ],
          'ua_footer.authorized' => [
            'name' => $node->field_ua_sm_authorizer_id->value,
            'email' => $node->field_ua_sm_authorizer_email->value,
          ],
          'system.ua_menu' => [
            'top_menu_style' => $node->field_ua_sm_top_menu_style->value,
          ],
        ],
      ];
      $yaml = Yaml::encode($config);
      return $yaml;
    }
    else {
      return FALSE;
    }
  }

}
