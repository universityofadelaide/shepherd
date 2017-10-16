<?php

namespace Drupal\shp_orchestration\Service;

use Drupal\node\NodeInterface;
use Drupal\shp_custom\Service\Site as SiteEntity;
use Drupal\shp_orchestration\OrchestrationProviderPluginManager;

/**
 * Class Site.
 */
class Site extends EntityActionBase {

  /**
   * @var \Drupal\shp_orchestration\OrchestrationProviderPluginManager
   */
  private $orchestrationProviderPluginManager;

  /**
   * @var \Drupal\shp_custom\Service\Site
   */
  private $siteEntity;

  /**
   * Shepherd constructor.
   *
   * @param \Drupal\shp_orchestration\OrchestrationProviderPluginManager $orchestrationProviderPluginManager
   * @param \Drupal\shp_custom\Service\Site $site
   */
  public function __construct(OrchestrationProviderPluginManager $orchestrationProviderPluginManager, SiteEntity $site) {
    parent::__construct($orchestrationProviderPluginManager);
    $this->siteEntity = $site;
  }

  /**
   * Tell the active orchestration provider a project was created.
   *
   * @param \Drupal\node\NodeInterface $site
   *
   * @return bool
   */
  public function created(NodeInterface $site) {
    $project = $this->siteEntity->getProject($site);
    return $this->orchestrationProviderPlugin->createdSite(
      $project->getTitle(),
      $site->field_shp_short_name->value,
      $site->id(),
      $site->field_shp_domain->value,
      $site->field_shp_path->value
    );
  }

  /**
   * Tell the active orchestration provider a project was updated.
   *
   * @param \Drupal\node\NodeInterface $node
   *
   * @return bool
   */
  public function updated(NodeInterface $node) {
    // @todo implement me as well.
    return TRUE;
  }

  /**
   * Tell the active orchestration provider a project was deleted.
   *
   * @param \Drupal\node\NodeInterface $site
   *
   * @return bool
   */
  public function deleted(NodeInterface $site) {
    $project = $this->siteEntity->getProject($site);
    return $this->orchestrationProviderPlugin->deletedSite(
      $project->getTitle(),
      $site->field_shp_short_name->value,
      $site->id()
    );
  }

}
