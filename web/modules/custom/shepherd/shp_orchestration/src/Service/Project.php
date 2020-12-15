<?php

namespace Drupal\shp_orchestration\Service;

use Drupal\node\NodeInterface;
use Drupal\shp_orchestration\OrchestrationProviderPluginManager;

/**
 * A service for interacting with project entities.
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
   * @param \Drupal\node\NodeInterface $project
   *   Project.
   *
   * @return bool
   *   True on success.
   */
  public function created(NodeInterface $project) {
    $environment_variables = $this->configuration->getProjectEnvironmentVariables($project);
    return $this->orchestrationProviderPlugin->createdProject(
      $project->getTitle(),
      $project->field_shp_builder_image->value,
      $project->field_shp_git_repository->value,
      $project->field_shp_git_default_ref->value,
      $project->field_shp_build_secret->value,
      $environment_variables
    );
  }

  /**
   * Tell the active orchestration provider a project was updated.
   *
   * @param \Drupal\node\NodeInterface $project
   *   Project.
   *
   * @return bool
   *   True on success.
   */
  public function updated(NodeInterface $project) {
    $environment_variables = $this->configuration->getProjectEnvironmentVariables($project);
    return $this->orchestrationProviderPlugin->updatedProject(
      $project->getTitle(),
      $project->field_shp_builder_image->value,
      $project->field_shp_git_repository->value,
      'master',
      $project->field_shp_build_secret->value,
      $environment_variables
    );
  }

  /**
   * Tell the active orchestration provider a project was deleted.
   *
   * @param \Drupal\node\NodeInterface $project
   *   Project.
   *
   * @return bool
   *   True on success.
   */
  public function deleted(NodeInterface $project) {
    // @todo implement me.
    return TRUE;
  }

}
