<?php

namespace Drupal\shp_content_types\Breadcrumb;

use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Link;
use Drupal\node\Entity\Node;

/**
 * Class SitesBreadcrumbBuilder
 * @package Drupal\shp_content_types\Breadcrumb
 */
class SitesBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  /**
   * Definition of view_ids that relate to a site.
   *
   * @var array
   */
  protected $sitesViews = [
    'shp_site_backups' => 'Backups',
    'shp_site_environments' => 'Environments',
    'shp_site_users' => 'Users',
  ];

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $parameters = $route_match->getParameters()->all();
    // Be more specific.
    if (array_key_exists('node', $parameters) && !empty($parameters['node'])
      && is_object($parameters['node'])) {
      $node = $parameters['node']->getType();
      return in_array($node, ['shp_site', 'shp_environment']) ? TRUE : FALSE;
    }
    if (array_key_exists('view_id', $parameters) && !empty($parameters['view_id'])) {
      return in_array($parameters['view_id'], array_keys($this->sitesViews)) ? TRUE : FALSE;
    }
    // Handle view specific stuff here.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addLink(Link::createFromRoute('Sites', '<front>'));
    $parameters = $route_match->getParameters()->all();
    $node = is_object($parameters['node']) ? $parameters['node'] : Node::load($parameters['node']);
    $node_type = $node->bundle();

    switch ($node_type) {
      case 'shp_site':
        $breadcrumb->addLink(Link::createFromRoute($node->getTitle(), 'entity.node.canonical', ['node' => $node->id()]));
        break;
      case 'shp_environment':
        $site = $node->get('field_shp_site')
          ->first()
          ->get('entity')
          ->getTarget()
          ->getValue();
        $breadcrumb->addLink(Link::createFromRoute($site->getTitle(), 'entity.node.canonical', ['node' => $site->id()]));
        break;
    }

    if (array_key_exists('view_id', $parameters)) {
      $breadcrumb->addLink(Link::createFromRoute($this->sitesViews[$parameters['view_id']], $route_match->getRouteName(), $parameters));
    }

    $breadcrumb->addCacheContexts(['route']);
    return $breadcrumb;
  }

}
