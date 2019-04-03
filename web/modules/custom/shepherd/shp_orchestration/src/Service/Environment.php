<?php

namespace Drupal\shp_orchestration\Service;

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\shp_custom\Service\Environment as EnvironmentEntity;
use Drupal\shp_custom\Service\EnvironmentTypeInterface;
use Drupal\shp_custom\Service\Site as SiteEntity;
use Drupal\shp_orchestration\Event\OrchestrationEnvironmentEvent;
use Drupal\shp_orchestration\Event\OrchestrationEvents;
use Drupal\shp_orchestration\OrchestrationProviderPluginManager;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
   * Environment service.
   *
   * @var \Drupal\shp_custom\Service\Environment|\Drupal\shp_orchestration\Service\Environment
   */
  protected $environmentEntity;

  /**
   * Site service.
   *
   * @var \Drupal\shp_custom\Service\Site
   */
  protected $siteEntity;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   *   Event dispatcher.
   */
  protected $eventDispatcher;

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
   *   Orchestration provider manager.
   * @param \Drupal\shp_orchestration\Service\Configuration $configuration
   *   Configuration service.
   * @param \Drupal\shp_custom\Service\Environment $environment
   *   Environment service.
   * @param \Drupal\shp_custom\Service\Site $site
   *   Site service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Event dispatcher.
   * @param \Drupal\shp_custom\Service\EnvironmentTypeInterface $environmentType
   *   Environment type service.
   */
  public function __construct(OrchestrationProviderPluginManager $orchestrationProviderPluginManager, Configuration $configuration, EnvironmentEntity $environment, SiteEntity $site, EventDispatcherInterface $event_dispatcher, EnvironmentTypeInterface $environmentType) {
    parent::__construct($orchestrationProviderPluginManager);
    $this->configuration = $configuration;
    $this->environmentEntity = $environment;
    $this->siteEntity = $site;
    $this->eventDispatcher = $event_dispatcher;
    $this->environmentType = $environmentType;
  }

  /**
   * Tell the active orchestration provider an environment was created.
   *
   * @todo - Extract some of the logic out of this method, too large.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node entity.
   *
   * @return \Drupal\node\NodeInterface|bool
   *   The environment node or FALSE on failure.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function created(NodeInterface $node) {
    $site = $this->environmentEntity->getSite($node);
    $project = $this->siteEntity->getProject($site);
    if (!isset($project) || !isset($site)) {
      return FALSE;
    }
    $environment_type = $this->environmentEntity->getEnvironmentType($node);

    $probes = $this->buildProbes($project);
    $cron_jobs = $this->buildCronJobs($node);

    $deployment_name = $this->orchestrationProviderPlugin::generateDeploymentName($node->id());

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
    $event = new OrchestrationEnvironmentEvent($this->orchestrationProviderPlugin, $deployment_name);
    $this->eventDispatcher->dispatch(OrchestrationEvents::SETUP_ENVIRONMENT, $event);
    if ($event_env_vars = $event->getEnvironmentVariables()) {
      $env_vars = array_merge($env_vars, $event_env_vars);
    }

    $storage_class = '';
    if ($project->field_shp_storage_class->target_id) {
      $storage_class = Term::load($project->field_shp_storage_class->target_id)->label();
    }

    // Extract and transform the annotations from the environment type.
    $annotations = $environment_type ? $environment_type->field_shp_annotations->getValue() : [];
    $annotations = array_combine(
      array_column($annotations, 'key'),
      array_column($annotations, 'value')
    );

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
      $storage_class,
      $node->field_shp_update_on_image_change->value,
      $node->field_shp_cron_suspended->value,
      $env_vars,
      $secrets,
      $probes,
      $cron_jobs,
      $annotations
    );

    // Allow other modules to react to the Environment creation.
    $event = new OrchestrationEnvironmentEvent($this->orchestrationProviderPlugin, $deployment_name, $site, $node, $project);
    $this->eventDispatcher->dispatch(OrchestrationEvents::CREATED_ENVIRONMENT, $event);

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
   *   Node entity.
   *
   * @return \Drupal\node\NodeInterface|bool
   *   The environment node or FALSE on failure.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function updated(NodeInterface $node) {
    $site = $this->environmentEntity->getSite($node);
    $project = $this->siteEntity->getProject($site);
    if (!isset($project) || !isset($site)) {
      return FALSE;
    }

    $probes = $this->buildProbes($project);
    $cron_jobs = $this->buildCronJobs($node);

    $deployment_name = $this->orchestrationProviderPlugin::generateDeploymentName($node->id());

    // Get environment variables and secrets.
    $env_vars = $this->configuration->getEnvironmentVariables($node);
    $secrets = $this->configuration->getSecrets($node);

    // Allow other modules to react to the Environment creation.
    $event = new OrchestrationEnvironmentEvent($this->orchestrationProviderPlugin, $deployment_name);
    $this->eventDispatcher->dispatch(OrchestrationEvents::SETUP_ENVIRONMENT, $event);
    if ($event_env_vars = $event->getEnvironmentVariables()) {
      $env_vars = array_merge($env_vars, $event_env_vars);
    }

    $storage_class = '';
    if ($project->field_shp_storage_class->target_id) {
      $storage_class = Term::load($project->field_shp_storage_class->target_id)->label();
    }

    $environment_updated = $this->orchestrationProviderPlugin->updatedEnvironment(
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
      $storage_class,
      $node->field_shp_update_on_image_change->value,
      $node->field_shp_cron_suspended->value,
      $env_vars,
      $secrets,
      $probes,
      $cron_jobs
    );

    // Allow other modules to react to the Environment update.
    $event = new OrchestrationEnvironmentEvent($this->orchestrationProviderPlugin, $deployment_name, $site, $node, $project);
    $this->eventDispatcher->dispatch(OrchestrationEvents::UPDATED_ENVIRONMENT, $event);

    return $environment_updated;
  }

  /**
   * Tell the active orchestration provider an environment was deleted.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node entity.
   *
   * @return bool
   *   True on success. False otherwise.
   */
  public function deleted(NodeInterface $node) {
    $site = $this->environmentEntity->getSite($node);
    $project = $this->siteEntity->getProject($site);
    if (!isset($project) || !isset($site)) {
      return FALSE;
    }

    $deployment_name = $this->orchestrationProviderPlugin->generateDeploymentName($node->id());

    $result = $this->orchestrationProviderPlugin->deletedEnvironment(
      $project->title->value,
      $site->field_shp_short_name->value,
      $node->id()
    );

    // Allow other modules to react to the Environment deletion.
    $event = new OrchestrationEnvironmentEvent($this->orchestrationProviderPlugin, $deployment_name);
    $this->eventDispatcher->dispatch(OrchestrationEvents::DELETED_ENVIRONMENT, $event);

    return $result;
  }

  /**
   * Tell the active orchestration provider an environment was promoted.
   *
   * @param \Drupal\node\NodeInterface $site
   *   Site entity.
   * @param \Drupal\node\NodeInterface $environment
   *   Environment entity.
   * @param bool $exclusive
   *   Send all traffic to this environment.
   * @param bool $clear_cache
   *   Clear cache.
   *
   * @return bool
   *   True on success. False otherwise.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function promoted(NodeInterface $site, NodeInterface $environment, bool $exclusive, bool $clear_cache = TRUE) {
    $project = $this->siteEntity->getProject($site);
    if (!isset($project) || !isset($site)) {
      return FALSE;
    }

    // Load the taxonomy term that has protect enabled.
    $promoted_term = $this->environmentType->getPromotedTerm();

    // Extract and transform the annotations from the environment type.
    $annotations = $promoted_term ? $promoted_term->field_shp_annotations->getValue() : [];
    $annotations = array_combine(
      array_column($annotations, 'key'),
      array_column($annotations, 'value')
    );

    $result = $this->orchestrationProviderPlugin->promotedEnvironment(
      $project->title->value,
      $site->field_shp_short_name->value,
      $site->id(),
      $environment->id(),
      $site->field_shp_domain->value,
      $site->field_shp_path->value,
      $annotations,
      $environment->field_shp_git_reference->value,
      $clear_cache
    );

    // @todo everything is exclusive for now, implement non-exclusive?

    // Load a non protected term.
    $demoted_term = $this->environmentType->getDemotedTerm();
    $promoted_term = $this->environmentType->getPromotedTerm();

    // Demote all current prod environments - for this site!
    $old_promoted = \Drupal::entityQuery('node')
      ->condition('field_shp_site', $site->id())
      ->condition('field_shp_environment_type', $promoted_term->id())
      ->condition('nid', $environment->id(), '<>')
      ->execute();
    foreach ($old_promoted as $nid) {
      $node = Node::load($nid);
      $node->field_shp_environment_type = [['target_id' => $demoted_term->id()]];
      $node->save();
    }

    // Finally Update the environment that was promoted if we need to.
    if ($environment->field_shp_environment_type->target_id != $promoted_term->id()) {
      $environment->field_shp_environment_type = [['target_id' => $promoted_term->id()]];
      $environment->save();
    }

    return $result;
  }

  /**
   * Constructs the probes configuration.
   *
   * @param \Drupal\node\NodeInterface $project
   *   Project entity.
   *
   * @return array
   *   Probes configuration
   */
  protected function buildProbes(NodeInterface $project) {
    $probes = [];

    foreach (['liveness', 'readiness'] as $type) {
      if ($project->get('field_shp_' . $type . '_probe_type')->value !== NULL) {
        $probes[$type] = [
          'type'       => $project->get('field_shp_' . $type . '_probe_type')->value,
          'port'       => $project->get('field_shp_' . $type . '_probe_port')->value,
          'parameters' => $project->get('field_shp_' . $type . '_probe_params')->value,
        ];
      }
    }

    return $probes;
  }

  /**
   * Constructs config by extracting the properties from field_shp_cron_jobs.
   *
   * @param \Drupal\node\NodeInterface $environment
   *   Environment entity.
   *
   * @return array
   *   Cron job array with extracted field properties.
   */
  protected function buildCronJobs(NodeInterface $environment) {
    $cron_jobs = [];

    foreach ($environment->field_shp_cron_jobs as $job) {
      $cron_jobs[$job->name] = [
        'cmd' => $job->value,
        'schedule' => $job->key,
      ];
    }

    return $cron_jobs;
  }

}
