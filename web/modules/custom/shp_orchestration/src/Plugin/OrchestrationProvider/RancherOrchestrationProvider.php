<?php

namespace Drupal\shp_orchestration\Plugin\OrchestrationProvider;

use Drupal\shp_orchestration\OrchestrationProviderBase;

/**
 * Class RancherOrchestrationProvider.
 *
 * @OrchestrationProvider(
 *   id = "rancher_orchestration_provider",
 *   name = "Rancher",
 *   description = @Translation("Rancher provider to perform orchestration tasks"),
 * )
 */
class RancherOrchestrationProvider extends OrchestrationProviderBase {

  /**
   * {@inheritdoc}
   */
  public function getFormat() {
    // @todo Implement getFormat() method.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    // @todo Implement getType() method.
    return "";
  }

}

