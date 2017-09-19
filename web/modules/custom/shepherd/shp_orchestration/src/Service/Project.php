<?php

namespace Drupal\shp_orchestration\Service;

use Drupal\node\NodeInterface;

/**
 * Class Project.
 * @package Drupal\shp_orchestration\Service
 */
class Project extends EntityActionBase {

  /**
   * Tell the active orchestration provider a project was created.
   *
   * @param \Drupal\node\NodeInterface $node
   * @return bool
   */
  public function created(NodeInterface $node) {
    return $this->orchestrationProviderPlugin->createdProject(
      $node->getTitle(),
      $node->field_shp_builder_image->value,
      $node->field_shp_git_repository->value,
      // @todo Consider fetching default source ref from config.
      'master',
      $node->field_shp_build_secret->value
    );
  }

  /**
   * Tell the active orchestration provider a project was updated.
   *
   * @param \Drupal\node\NodeInterface $node
   * @return bool
   */
  public function updated(NodeInterface $node) {
    return $this->orchestrationProviderPlugin->updatedProject(
      $node->getTitle(),
      $node->field_shp_builder_image->value,
      $node->field_shp_git_repository->value,
      'master',
      $node->field_shp_build_secret->value
    );
  }

  /**
   * Tell the active orchestration provider a project was deleted.
   *
   * @param \Drupal\node\NodeInterface $node
   * @return bool
   */
  public function deleted(NodeInterface $node) {
    // @todo implement me.
    return TRUE;
  }

}
