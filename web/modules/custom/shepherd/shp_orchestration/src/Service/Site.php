<?php

namespace Drupal\shp_orchestration\Service;

use Drupal\node\NodeInterface;
use Drupal\shp_orchestration\OrchestrationProviderTrait;

/**
 * Class Project.
 */
class Site extends EntityActionBase {

  use OrchestrationProviderTrait;

  /**
   * Tell the active orchestration provider a project was created.
   *
   * @param \Drupal\node\NodeInterface $site
   *
   * @return bool
   */
  public function created(NodeInterface $site) {
    $project = $this->getProjectFromSite($site);
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
   * @param \Drupal\node\NodeInterface $node
   *
   * @return bool
   */
  public function deleted(NodeInterface $node) {
    // @todo implement me.
    return TRUE;
  }

}
