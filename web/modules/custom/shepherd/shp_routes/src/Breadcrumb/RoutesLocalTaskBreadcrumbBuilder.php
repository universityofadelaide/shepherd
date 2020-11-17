<?php

namespace Drupal\shp_routes\Breadcrumb;

use Drupal\shp_content_types\Breadcrumb\SitesBreadcrumbBuilder;

/**
 * Extends custom Breadcrumb builder to add the Routes view.
 */
class RoutesLocalTaskBreadcrumbBuilder extends SitesBreadcrumbBuilder {

  /**
   * {@inheritdoc}
   */
  protected $sitesViews = [
    'shp_site_routes' => 'Routes',
  ];

}
