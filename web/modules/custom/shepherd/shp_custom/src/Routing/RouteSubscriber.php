<?php

namespace Drupal\shp_custom\Routing;

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
    if ($route = $collection->get('view.shp_site_environments.page_1')) {
      $route->setRequirement('_custom_access', '\Drupal\shp_custom\Controller\SiteLocalTaskController::checkAccess');
    }

    if ($route = $collection->get('view.shp_site_users.page_1')) {
      $route->setRequirement('_custom_access', '\Drupal\shp_custom\Controller\SiteLocalTaskController::checkAccess');
    }

    if ($route = $collection->get('')) {
      $route->setDefault('_form', '\Drupal\form_overwrite\Form\NewUserLoginForm');
    }
  }

}
