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
    $parameters = $this->pluginDefinition['route_parameters'] ?? [];
    $route = $this->routeProvider->getRouteByName($this->getRouteName());
    $variables = $route->compile()->getVariables();

    foreach ($variables as $name) {
      if ($name === 'group') {
        // Support both 'node' and 'arg_0' as the contextual parameter on
        // Views-driven pages across Drupal versions.
        $id = $route_match->getRawParameter('node');
        if ($id === NULL) {
          $id = $route_match->getRawParameter('arg_0');
        }
        $node = $id ? Node::load($id) : NULL;

        /** @var \Drupal\group\Entity\GroupInterface $group */
        if ($node) {
          $group = \Drupal::service('shp_content_types.group_manager')->load($node);
          if ($group) {
            $parameters['group'] = $group->id();
          }
        }
      }
    }

    return $parameters;
  }

}
