<?php

namespace Drupal\shp_custom\Plugin\Menu\LocalAction;

use Drupal\group\Plugin\Menu\LocalAction\WithDestination;
use Drupal\node\Entity\Node;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Defines a local action plugin with a dynamic title.
 */
class NodeToGroup extends WithDestination {

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters(RouteMatchInterface $route_match) {
    $parameters = isset($this->pluginDefinition['route_parameters']) ? $this->pluginDefinition['route_parameters'] : [];
    $route = $this->routeProvider->getRouteByName($this->getRouteName());
    $variables = $route->compile()->getVariables();

    foreach ($variables as $name) {
      if ($name == 'group') {
        $id = $route_match->getRawParameter('node');
        $node = Node::load($id);

        /** @var \Drupal\group\Entity\GroupInterface $group */
        $group = \Drupal::service('shp_content_types.group_manager')->load($node);
        $parameters['group'] = $group->id();
      }
    }

    return $parameters;
  }

}
