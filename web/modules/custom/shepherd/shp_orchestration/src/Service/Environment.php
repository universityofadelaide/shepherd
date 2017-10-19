<?php

namespace Drupal\shp_orchestration\Service;

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\shp_custom\Service\Environment as EnvironmentEntity;
use Drupal\shp_custom\Service\Site as SiteEntity;
use Drupal\shp_orchestration\Event\OrchestrationEnvironmentEvent;
use Drupal\shp_orchestration\Event\OrchestrationEvents;
use Drupal\shp_orchestration\OrchestrationProviderPluginManager;
use Drupal\taxonomy\Entity\Term;

/**
 * Class Environment.
 */
class Environment extends EntityActionBase {

  /**
   * The Shepherd configuration service.
   *
   * @var \Drupal\shp_orchestration\Service\Configuration
   */
  protected $configuration;

  /**
   * @var \Drupal\shp_orchestration\OrchestrationProviderPluginManager
   */
  private $orchestrationProviderPluginManager;

  /**
   * @var \Drupal\shp_custom\Service\Environment|\Drupal\shp_orchestration\Service\Environment
   */
  private $environmentEntity;

  /**
   * @var \Drupal\shp_custom\Service\Site
   */
  private $siteEntity;

  /**
   * Shepherd constructor.
   *
   * @param \Drupal\shp_orchestration\OrchestrationProviderPluginManager $orchestrationProviderPluginManager
   * @param \Drupal\shp_orchestration\Service\Configuration $configuration
   * @param \Drupal\shp_custom\Service\Environment $environment
   * @param \Drupal\shp_custom\Service\Site $site
   */
  public function __construct(OrchestrationProviderPluginManager $orchestrationProviderPluginManager, Configuration $configuration, EnvironmentEntity $environment, SiteEntity $site) {
    parent::__construct($orchestrationProviderPluginManager);
    $this->configuration = $configuration;
    $this->environmentEntity = $environment;
    $this->siteEntity = $site;
  }

  /**
   * Tell the active orchestration provider an environment was created.
   *
   * @param \Drupal\node\NodeInterface $node
   *
   * @return bool
   */
  public function created(NodeInterface $node) {
    $site = $this->environmentEntity->getSite($node);
    $project = $this->siteEntity->getProject($site);
    if (!isset($project) || !isset($site)) {
      return FALSE;
    }

    $probes = [];
    foreach (['liveness', 'readiness'] as $type) {
      $probes[$type] = [
        'type'       => $project->get('field_shp_' . $type . '_probe_type')->value,
        'port'       => $project->get('field_shp_' . $type . '_probe_port')->value,
        'parameters' => $project->get('field_shp_' . $type . '_probe_params')->value,
      ];
    }

    $cron_jobs = [];
    foreach ($node->field_shp_cron_jobs as $job) {
      $cron_jobs[$job->key] = $job->value;
    }

    $deployment_name = $this->orchestrationProviderPlugin::generateDeploymentName(
      $project->getTitle(),
      $site->field_shp_short_name->value,
      $node->id()
    );

    // Generate an auth token and add it to the secret associated with the
    // environment. Create the secret if it doesn't exist.
    // @todo Replace with generated Shepherd auth token.
    $shepherd_token = 'super-secret-token';
    if ($env_secret = $this->orchestrationProviderPlugin->getSecret($deployment_name)) {
      $secret_result = $this->orchestrationProviderPlugin->updateSecret(
        $deployment_name,
        array_merge($env_secret, ['SHEPHERD_TOKEN' => $shepherd_token])
      );
    }
    else {
      $secret_result = $this->orchestrationProviderPlugin->createSecret(
        $deployment_name,
        ['SHEPHERD_TOKEN' => $shepherd_token]
      );
    }
    if (!$secret_result) {
      // @todo Handle errors.
      return FALSE;
    }

    // Get environment variables and secrets.
    $env_vars = $this->configuration->getEnvironmentVariables($node);
    $secrets = $this->configuration->getSecrets($node);

    // Allow other modules to react to the Environment creation.
    $eventDispatcher = \Drupal::service('event_dispatcher');
    $event = new OrchestrationEnvironmentEvent($this->orchestrationProviderPlugin, $deployment_name);
    $eventDispatcher->dispatch(OrchestrationEvents::SETUP_ENVIRONMENT, $event);
    if ($event_env_vars = $event->getEnvironmentVariables()) {
      $env_vars = array_merge($env_vars, $event_env_vars);
    }

    $environment = $this->orchestrationProviderPlugin->createdEnvironment(
      $project->getTitle(),
      $site->field_shp_short_name->value,
      $site->id(),
      $node->id(),
      $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
      $project->field_shp_builder_image->value,
      $node->field_shp_domain->value,
      $node->field_shp_path->value,
      $project->field_shp_git_repository->value,
      $node->field_shp_git_reference->value,
      $project->field_shp_build_secret->value,
      $node->field_shp_update_on_image_change->value,
      $env_vars,
      $secrets,
      $probes,
      $cron_jobs
    );

    // Allow other modules to react to the Environment creation.
    $event = new OrchestrationEnvironmentEvent($this->orchestrationProviderPlugin, $deployment_name, $site, $node, $project);
    $eventDispatcher->dispatch(OrchestrationEvents::CREATED_ENVIRONMENT, $event);

    // If this is a production environment, promote it immediately.
    $environment_term = Term::load($node->field_shp_environment_type->target_id);
    if ($environment_term->field_shp_protect->value == TRUE) {
      $this->promoted($site, $node, TRUE, FALSE);
    }

    return $environment;
  }

  /**
   * Tell the active orchestration provider an environment was updated.
   *
   * @param \Drupal\node\NodeInterface $node
   *
   * @return bool
   */
  public function updated(NodeInterface $node) {
    // @todo implement me.
    return TRUE;
  }

  /**
   * Tell the active orchestration provider an environment was deleted.
   *
   * @param \Drupal\node\NodeInterface $node
   *
   * @return bool
   */
  public function deleted(NodeInterface $node) {
    $site = $this->environmentEntity->getSite($node);
    $project = $this->siteEntity->getProject($site);
    if (!isset($project) || !isset($site)) {
      return FALSE;
    }

    $deployment_name = $this->orchestrationProviderPlugin->generateDeploymentName(
      $project->getTitle(),
      $site->field_shp_short_name->value,
      $node->id()
    );

    $result = $this->orchestrationProviderPlugin->deletedEnvironment(
      $project->title->value,
      $site->field_shp_short_name->value,
      $node->id()
    );

    // Allow other modules to react to the Environment deletion.
    $eventDispatcher = \Drupal::service('event_dispatcher');
    $event = new OrchestrationEnvironmentEvent($this->orchestrationProviderPlugin, $deployment_name);
    $eventDispatcher->dispatch(OrchestrationEvents::DELETED_ENVIRONMENT, $event);

    return $result;
  }

  /**
   * @param \Drupal\node\NodeInterface $site
   * @param \Drupal\node\NodeInterface $environment
   * @param bool $exclusive
   * @param bool $clear_cache
   *
   * @return bool
   */
  public function promoted(NodeInterface $site, NodeInterface $environment, bool $exclusive, bool $clear_cache = TRUE) {
    $project = $this->siteEntity->getProject($site);
    if (!isset($project) || !isset($site)) {
      return FALSE;
    }

    $result = $this->orchestrationProviderPlugin->promotedEnvironment(
      $project->title->value,
      $site->field_shp_short_name->value,
      $site->id(),
      $environment->id(),
      $environment->field_shp_git_reference->value,
      $clear_cache
    );

    // @todo everything is exclusive for now, implement non-exclusive?

    // Load a non protected term
    // @todo handle multiples? this is quite horrid.
    $ids = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', 'shp_environment_types')
      ->condition('field_shp_protect', FALSE)
      ->execute();
    $demoted_term = reset(Term::loadMultiple($ids));

    // Load the taxonomy term that has protect enabled.
    $ids = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', 'shp_environment_types')
      ->condition('field_shp_protect', TRUE)
      ->execute();
    $promoted_term = reset(Term::loadMultiple($ids));

    // Demote all current prod environments
    $old_promoted = \Drupal::entityQuery('node')
      ->condition('field_shp_environment_type', $promoted_term->id())
      ->condition('nid', $environment->id(), '<>')
      ->execute();
    foreach ($old_promoted as $nid) {
      $node = Node::load($nid);
      $node->field_shp_environment_type = [['target_id' => $demoted_term->id()]];
      $node->save();
    }

    // Finally Update the environment that was promoted if we need to
    if ($environment->field_shp_environment_type->target_id != $promoted_term->id()) {
      $environment->field_shp_environment_type = [['target_id' => $promoted_term->id()]];
      $environment->save();
    }

    return $result;
  }

}
