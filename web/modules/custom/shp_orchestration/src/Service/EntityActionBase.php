<?php

namespace Drupal\shp_orchestration\Service;

use Drupal\shp_orchestration\Exception\OrchestrationProviderNotConfiguredException;
use Drupal\shp_orchestration\OrchestrationProviderPluginManager;

/**
 * Class EntityBase.
 * @package Drupal\shp_orchestration\Service
 */
class EntityActionBase {
  /**
   * The currently active orchestration provider plugin.
   *
   * @var \Drupal\shp_orchestration\OrchestrationProviderInterface
   */
  protected $orchestrationProviderPlugin;

  /**
   * EntityBase constructor.
   *
   * @param \Drupal\shp_orchestration\OrchestrationProviderPluginManager $orchestrationProviderPluginManager
   */
  public function __construct(OrchestrationProviderPluginManager $orchestrationProviderPluginManager) {
    try {
      $this->orchestrationProviderPlugin = $orchestrationProviderPluginManager->getProviderInstance();
    }
    catch (OrchestrationProviderNotConfiguredException $e) {
      drupal_set_message($e->getMessage(), 'warning');
    }
  }

}
