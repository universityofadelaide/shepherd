<?php

namespace Drupal\shp_orchestration\Service;

use Drupal\node\NodeInterface;
use Drupal\shp_custom\Service\Environment as EnvironmentEntity;
use Drupal\shp_custom\Service\Site as SiteEntity;
use Drupal\shp_orchestration\OrchestrationProviderPluginManager;

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
   * Environment service.
   *
   * @var \Drupal\shp_custom\Service\Environment|\Drupal\shp_orchestration\Service\Environment
   */
  protected $environmentEntity;

  /**
   * Site service.
   *
   * @var \Drupal\shp_custom\Service\Site
   */
  protected $siteEntity;

  /**
   * Status constructor.
   *
   * @param \Drupal\shp_orchestration\OrchestrationProviderPluginManager $orchestrationProviderPluginManager
   *   The orchestration provider manager.
   * @param \Drupal\shp_custom\Service\Environment $environment
   *   Environment service.
   * @param \Drupal\shp_custom\Service\Site $site
   *   Site service.
   */
  public function __construct(OrchestrationProviderPluginManager $orchestrationProviderPluginManager, EnvironmentEntity $environment, SiteEntity $site) {
    $this->orchestrationProviderPlugin = $orchestrationProviderPluginManager->getProviderInstance();
    $this->environmentEntity = $environment;
    $this->siteEntity = $site;
  }

  /**
   * Retrieves site environments status via orchestrationProvider.
   *
   * @param \Drupal\node\NodeInterface $environment
   *   The site node entity.
   *
   * @return array
   *   All environment statuses.
   */
  public function get(NodeInterface $environment) {
    $site = $this->environmentEntity->getSite($environment);
    $project = $this->siteEntity->getProject($site);
    return $this->orchestrationProviderPlugin->getEnvironmentStatus($project->getTitle(), $site->field_shp_short_name->value, $environment->id());
  }

}
