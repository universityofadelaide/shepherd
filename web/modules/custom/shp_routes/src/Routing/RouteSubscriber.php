<?php

namespace Drupal\shp_routes\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('view.shp_site_routes.page_1')) {
      $route->setRequirement('_custom_access', '\Drupal\shp_custom\Controller\SiteLocalTaskController::checkAccess');
    }
  }

}
