<?php

namespace Drupal\shp_orchestration;

use Drupal\node\NodeInterface;

trait OrchestrationProviderTrait {

  /**
   * @param \Drupal\node\NodeInterface $site
   *
   * @return \Drupal\node\NodeInterface|bool
   */
  protected function getProjectFromSite(NodeInterface $site) {
    if (isset($site->field_shp_project->target_id)) {
      /** @var \Drupal\node\NodeInterface $project */
      return $site->get('field_shp_project')
        ->first()
        ->get('entity')
        ->getTarget()
        ->getValue();
    }

    return FALSE;
  }
}
