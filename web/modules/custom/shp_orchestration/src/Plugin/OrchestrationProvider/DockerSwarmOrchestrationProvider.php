<?php

namespace Drupal\shp_orchestration\Plugin\OrchestrationProvider;

use Drupal\shp_orchestration\OrchestrationProviderBase;

/**
 * Class DockerSwarmOrchestrationProvider.
 *
 * @OrchestrationProvider(
 *   id = "dockerswarm_orchestration_provider",
 *   name = "Docker Swarm",
 *   description = @Translation("Docker swarm provider to perform orchestration tasks"),
 * )
 */
class DockerSwarmOrchestrationProvider extends OrchestrationProviderBase {

  /**
   * {@inheritdoc}
   */
  public function getFormat() {
    // TODO: Implement getFormat() method.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    // TODO: Implement getType() method.
    return "";
  }

}

