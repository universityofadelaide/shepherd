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
    $site_param = $route_match->getRawParameter('node');
    if ($site_param === NULL) {
      $site_param = $route_match->getRawParameter('arg_0');
    }
    $parameters['site_id'] = $site_param;
    return $parameters;
  }

}
