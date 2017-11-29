<?php

namespace Drupal\shp_orchestration\Service;

use Drupal\node\NodeInterface;
use Drupal\shp_orchestration\OrchestrationProviderPluginManager;
use Drupal\shp_orchestration\Exception\OrchestrationProviderNotConfiguredException;

/**
 * Class Status.
 *
 * @package Drupal\shp_orchestration\Service
 */
class Status {

  /**
   * The currently active orchestration provider plugin.
   *
   * @var \Drupal\shp_orchestration\OrchestrationProviderInterface
   */
  protected $orchestrationProviderPlugin;

  /**
   * Status constructor.
   *
   * @param \Drupal\shp_orchestration\OrchestrationProviderPluginManager $orchestrationProviderPluginManager
   *   The orchestration provider manager.
   */
  public function __construct(OrchestrationProviderPluginManager $orchestrationProviderPluginManager) {
    try {
      $this->orchestrationProviderPlugin = $orchestrationProviderPluginManager->getProviderInstance();
    }
    catch (OrchestrationProviderNotConfiguredException $e) {
      drupal_set_message($e->getMessage(), 'warning');
    }
  }

  /**
   * Retrieves site environments status via orchestrationProvider.
   *
   * @param \Drupal\node\NodeInterface $site
   *   The site node entity.
   *
   * @return array
   *   All environment statuses.
   */
  public function get(NodeInterface $site) {
    return $this->orchestrationProviderPlugin->getSiteEnvironmentsStatus($site->id());
  }

}
