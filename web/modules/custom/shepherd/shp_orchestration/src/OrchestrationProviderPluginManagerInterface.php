<?php

namespace Drupal\shp_orchestration;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Class OrchestrationProviderPluginManager.
 *
 * @package Drupal\shp_orchestration
 */
interface OrchestrationProviderPluginManagerInterface extends PluginManagerInterface {

  /**
   * Retrieves the stored provider from config store.
   *
   * @return array
   *   Stored provider definition.
   */
  public function getSelectedProvider();

  /**
   * Creates and returns stored provider instance.
   *
   * @return object
   *   The orchestration provider plugin instance.
   */
  public function getProviderInstance();

}
