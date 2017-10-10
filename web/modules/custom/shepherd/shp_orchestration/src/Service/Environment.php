<?php

namespace Drupal\shp_orchestration\Service;

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\shp_orchestration\Event\OrchestrationEnvironmentEvent;
use Drupal\shp_orchestration\Event\OrchestrationEvents;
use Drupal\shp_orchestration\OrchestrationProviderPluginManager;
use Drupal\shp_orchestration\OrchestrationProviderTrait;
use Drupal\taxonomy\Entity\Term;

/**
 * Class Environment.
 */
class Environment extends EntityActionBase {

  use OrchestrationProviderTrait;

  /**
   * The Shepherd configuration service.
   *
   * @var \Drupal\shp_orchestration\Service\Configuration
   */
  protected $configuration;

  /**
   * Shepherd constructor.
   *
   * @param \Drupal\shp_orchestration\OrchestrationProviderPluginManager $orchestrationProviderPluginManager
   * @param \Drupal\shp_orchestration\Service\Configuration $configuration
   */
  public function __construct(OrchestrationProviderPluginManager $orchestrationProviderPluginManager, Configuration $configuration) {
    parent::__construct($orchestrationProviderPluginManager);
    $this->configuration = $configuration;
  }

  /**
   * Tell the active orchestration provider an environment was created.
   *
   * @param \Drupal\node\NodeInterface $node
   *
   * @return bool
   */
  public function created(NodeInterface $node) {
    $site = $this->getSiteFromEnvironment($node);
    $project = $this->getProjectFromSite($site);
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
      $env_vars,
      $secrets,
      $probes,
      $cron_jobs
    );

    // Allow other modules to react to the Environment creation.
    $event = new OrchestrationEnvironmentEvent($this->orchestrationProviderPlugin, $deployment_name, $site, $node, $project);
    $eventDispatcher->dispatch(OrchestrationEvents::CREATED_ENVIRONMENT, $event);

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
    $site = $this->getSiteFromEnvironment($node);
    $project = $this->getProjectFromSite($site);
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
   *
   * @return bool
   */
  public function promote(NodeInterface $site, NodeInterface $environment, bool $exclusive) {
    $project = $this->getProjectFromSite($site);
    if (!isset($project) || !isset($site)) {
      return FALSE;
    }

    $result = $this->orchestrationProviderPlugin->promotedEnvironment(
      $project->title->value,
      $site->field_shp_short_name->value,
      $site->id(),
      $environment->id()
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
      ->execute();
    foreach ($old_promoted as $nid) {
      $node = Node::load($nid);
      $node->field_shp_environment_type = [['target_id' => $demoted_term->id()]];
      $node->save();
    }

    // Finally Update the environment that was promoted.
    $environment->field_shp_environment_type = [['target_id' => $promoted_term->id()]];
    $environment->save();

    return $result;
  }

  /**
   * @param \Drupal\node\NodeInterface $environment
   *
   * @return \Drupal\node\NodeInterface|bool
   */
  protected function getSiteFromEnvironment(NodeInterface $environment) {
    if (isset($environment->field_shp_site->target_id)) {
      return $environment->get('field_shp_site')
        ->first()
        ->get('entity')
        ->getTarget()
        ->getValue();
    }

    return FALSE;
  }

}
