<?php

namespace Drupal\shp_backup\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alter routes to set custom access.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('view.shp_backup_list.page_1')) {
      $route->setRequirement('_custom_access', '\Drupal\shp_custom\Controller\SiteLocalTaskController::checkAccess');
    }
  }

}
