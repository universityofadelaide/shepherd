<?php

namespace Drupal\shp_orchestration\Service;

use Drupal\node\NodeInterface;

/**
 * Class Distribution.
 * @package Drupal\shp_orchestration\Service
 */
class Distribution extends EntityActionBase {

  /**
   * Tell the active orchestration provider a distribution was created.
   *
   * @param \Drupal\node\NodeInterface $node
   * @return bool
   */
  public function created(NodeInterface $node) {
    return $this->orchestrationProviderPlugin->createdDistribution(
      $node->getTitle(),
      $node->field_shp_builder_image->value,
      $node->field_shp_git_repository->value,
      // @todo Consider fetching default source ref from config.
      'master',
      $node->field_shp_build_secret->value
    );
  }

  /**
   * Tell the active orchestration provider a distribution was updated.
   *
   * @param \Drupal\node\NodeInterface $node
   * @return bool
   */
  public function updated(NodeInterface $node) {
    return $this->orchestrationProviderPlugin->updatedDistribution(
      $node->getTitle(),
      $node->field_shp_builder_image->value,
      $node->field_shp_git_repository->value,
      'master',
      $node->field_shp_build_secret->value
    );
  }

  /**
   * Tell the active orchestration provider a distribution was deleted.
   *
   * @param \Drupal\node\NodeInterface $node
   * @return bool
   */
  public function deleted(NodeInterface $node) {
    // @todo implement me.
    return TRUE;
  }

}
