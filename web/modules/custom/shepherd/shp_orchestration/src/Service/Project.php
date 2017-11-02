<?php

namespace Drupal\shp_orchestration\Service;

use Drupal\node\NodeInterface;
use Drupal\shp_orchestration\OrchestrationProviderPluginManager;

/**
 * Class Project.
 */
class Project extends EntityActionBase {

  /**
   * Configuration service.
   *
   * @var \Drupal\shp_orchestration\Service\Configuration
   */
  private $configuration;

  /**
   * EntityBase constructor.
   *
   * @param \Drupal\shp_orchestration\OrchestrationProviderPluginManager $orchestrationProviderPluginManager
   *   The orchestration provider manager.
   * @param \Drupal\shp_orchestration\Service\Configuration $configuration
   *   Configuration service.
   */
  public function __construct(OrchestrationProviderPluginManager $orchestrationProviderPluginManager, Configuration $configuration) {
    parent::__construct($orchestrationProviderPluginManager);
    $this->configuration = $configuration;
  }

  /**
   * Tell the active orchestration provider a project was created.
   *
   * @param \Drupal\node\NodeInterface $node
   *
   * @return bool
   */
  public function created(NodeInterface $node) {
    $environment_variables = $this->configuration->getProjectEnvironmentVariables($node);
    return $this->orchestrationProviderPlugin->createdProject(
      $node->getTitle(),
      $node->field_shp_builder_image->value,
      $node->field_shp_git_repository->value,
      // @todo Consider fetching default source ref from config.
      'master',
      $node->field_shp_build_secret->value,
      $environment_variables
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
    $environment_variables = $this->configuration->getProjectEnvironmentVariables($node);
    return $this->orchestrationProviderPlugin->updatedProject(
      $node->getTitle(),
      $node->field_shp_builder_image->value,
      $node->field_shp_git_repository->value,
      'master',
      $node->field_shp_build_secret->value,
      $environment_variables
    );
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
