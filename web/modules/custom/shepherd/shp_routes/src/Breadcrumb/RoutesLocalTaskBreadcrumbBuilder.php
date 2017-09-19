<?php

namespace Drupal\shp_routes\Breadcrumb;

use Drupal\shp_content_types\Breadcrumb\SitesBreadcrumbBuilder;

/**
 * Class RoutesLocalTaskBreadcrumbBuilder
 * @package Drupal\shp_routes\Breadcrumb
 */
class RoutesLocalTaskBreadcrumbBuilder extends SitesBreadcrumbBuilder {

  /**
   * {@inheritdoc}
   */
  protected $sitesViews = [
    'shp_site_routes' => 'Routes',
  ];

}
