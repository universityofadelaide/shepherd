<?php

namespace Drupal\shp_drush_aliases\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Component\Transliteration\PhpTransliteration;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class SiteAliasesController
 * @package Drupal\shp_drush_aliases\Controller
 */
class SiteAliasesController extends ControllerBase {

  public function siteAliases($nid) {
    $node = Node::load($nid);
    $aliases = \Drupal::service('shp_drush_aliases.site_aliases')->generateAliases($node);
    if ($aliases) {
      // @todo Site machine name?
      // Stolen from Drupal\migrate\Plugin\migrate\process\MachineName.
      $trans = new PHPTransliteration();
      $site_machine_name = $trans->transliterate($node->title->value, LanguageInterface::LANGCODE_DEFAULT, '_');
      $site_machine_name = strtolower($site_machine_name);
      $site_machine_name = preg_replace('/[^a-z0-9_]+/', '_', $site_machine_name);
      $site_machine_name = preg_replace('/_+/', '_', $site_machine_name);
      $filename = $site_machine_name . '.aliases.drushrc.php';

      $response = new Response($aliases);
      $response->headers->set('Content-Type', 'application/php');
      $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
      return $response;
    }
    else {
      throw new NotFoundHttpException();
    }
  }

}
