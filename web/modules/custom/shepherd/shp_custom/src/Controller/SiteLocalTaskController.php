<?php

namespace Drupal\shp_custom\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;


/**
 * Class SiteLocalTaskController
 * @package Drupal\shp_custom\Controller
 */
class SiteLocalTaskController extends ControllerBase {

  /**
   * Checks access on routes to display local tasks.
   *
   * @param string|object $node
   *    Node id to check access on.
   *
   * @return \Drupal\Core\Access\AccessResult
   *    If true access is allowed.
   */
  public function checkAccess($node) {
    if (!is_object($node)) {
      $node = Node::load($node);
    }
    return AccessResult::allowedIf($node->bundle() === "shp_site");
  }

}
