<?php

namespace Drupal\shp_orchestration\Service;

use Drupal\node\NodeInterface;
use Drupal\shp_custom\Service\EnvironmentTypeInterface;
use Drupal\shp_custom\Service\Site as SiteEntity;
use Drupal\shp_orchestration\OrchestrationProviderPluginManager;

/**
 * A service for interacting with site entities.
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
   * Tell the active orchestration provider a site was created.
   *
   * @param \Drupal\node\NodeInterface $site
   *   Site.
   *
   * @return bool
   *   True on success.
   */
  public function created(NodeInterface $site) {
    $project = $this->siteEntity->getProject($site);

    $serviceAccount = $this->orchestrationProviderPlugin->createdSite(
      $project->getTitle(),
      $site->field_shp_short_name->value,
      $site->id(),
      $site->field_shp_domain->value,
      $site->field_shp_path->value
    );

    // This is saving which partway through the insert. Explosive?
    $site->field_shp_service_account->value = $serviceAccount->label();
    $site->save();

    return TRUE;
  }

  /**
   * Tell the active orchestration provider a site was updated.
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
   * Tell the active orchestration provider a site was deleted.
   *
   * @param \Drupal\node\NodeInterface $site
   *   Site.
   *
   * @return bool
   *   True on success.
   */
  public function deleted(NodeInterface $site) {
    if ($project = $this->siteEntity->getProject($site)) {
      return $this->orchestrationProviderPlugin->deletedSite(
        $project->getTitle(),
        $site->field_shp_short_name->value,
        $site->id()
      );
    }
    return FALSE;
  }

}
