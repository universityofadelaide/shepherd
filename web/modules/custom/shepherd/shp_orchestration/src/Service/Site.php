<?php

namespace Drupal\shp_orchestration\Service;

use Drupal\node\NodeInterface;
use Drupal\shp_custom\Service\EnvironmentTypeInterface;
use Drupal\shp_custom\Service\Site as SiteEntity;
use Drupal\shp_orchestration\OrchestrationProviderPluginManager;

/**
 * Class Site.
 */
class Site extends EntityActionBase {

  /**
   * Site service.
   *
   * @var \Drupal\shp_custom\Service\Site
   */
  protected $siteEntity;

  /**
   * Environment type service.
   *
   * @var \Drupal\shp_custom\Service\EnvironmentTypeInterface
   */
  protected $environmentType;

  /**
   * Shepherd constructor.
   *
   * @param \Drupal\shp_orchestration\OrchestrationProviderPluginManager $orchestrationProviderPluginManager
   *   Orchestration provider plugin manager.
   * @param \Drupal\shp_custom\Service\Site $site
   *   Site service.
   * @param \Drupal\shp_custom\Service\EnvironmentTypeInterface $environmentType
   *   Environment type service.
   */
  public function __construct(OrchestrationProviderPluginManager $orchestrationProviderPluginManager, SiteEntity $site, EnvironmentTypeInterface $environmentType) {
    parent::__construct($orchestrationProviderPluginManager);
    $this->siteEntity = $site;
    $this->environmentType = $environmentType;
  }

  /**
   * Tell the active orchestration provider a project was created.
   *
   * @param \Drupal\node\NodeInterface $site
   *   Site.
   *
   * @return bool
   *   True on success.
   */
  public function created(NodeInterface $site) {
    $project = $this->siteEntity->getProject($site);

    // Load the taxonomy term that has protect enabled.
    $promoted_term = $this->environmentType->getPromotedTerm();

    // Extract and transform the annotations from the environment type.
    $annotations = $promoted_term ? $promoted_term->field_shp_annotations->getValue() : [];
    $annotations = array_combine(
      array_column($annotations, 'key'),
      array_column($annotations, 'value')
    );

    return $this->orchestrationProviderPlugin->createdSite(
      $project->getTitle(),
      $site->field_shp_short_name->value,
      $site->id(),
      $site->field_shp_domain->value,
      $site->field_shp_path->value,
      $annotations
    );
  }

  /**
   * Tell the active orchestration provider a project was updated.
   *
   * @param \Drupal\node\NodeInterface $site
   *   Site.
   *
   * @return bool
   *   True on success.
   */
  public function updated(NodeInterface $site) {
    // @todo implement me as well.
    return TRUE;
  }

  /**
   * Tell the active orchestration provider a project was deleted.
   *
   * @param \Drupal\node\NodeInterface $site
   *   Site.
   *
   * @return bool
   *   True on success.
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
