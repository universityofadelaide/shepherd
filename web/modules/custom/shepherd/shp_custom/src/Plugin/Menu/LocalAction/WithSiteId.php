<?php

namespace Drupal\shp_custom\Plugin\Menu\LocalAction;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\group\Plugin\Menu\LocalAction\WithDestination;

/**
 * Modifies the Local Action to add shepherd site id.
 */
class WithSiteId extends WithDestination {

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters(RouteMatchInterface $route_match) {
    $parameters = parent::getRouteParameters($route_match);
    $parameters['site_id'] = $route_match->getParameter('node');
    return $parameters;
  }

}
