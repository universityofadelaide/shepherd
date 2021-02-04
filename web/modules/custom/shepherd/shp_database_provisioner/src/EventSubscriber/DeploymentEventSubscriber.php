<?php

namespace Drupal\shp_database_provisioner\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\shp_orchestration\Event\OrchestrationEnvironmentEvent;
use Drupal\shp_orchestration\Event\OrchestrationEvents;
use Drupal\token\TokenInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to deployment events.
 */
class DeploymentEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Backup settings.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Used to retrieve config.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Used to expand tokens from config into usable strings.
   *
   * @var \Drupal\token\Token
   */
  protected $token;

  /**
   * DeploymentEventSubscriber constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\token\TokenInterface $token
   *   Token service.
   */
  public function __construct(ConfigFactoryInterface $configFactory, TokenInterface $token) {
    $this->configFactory = $configFactory;
    $this->config = $this->configFactory->get('shp_database_provisioner.settings');
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[OrchestrationEvents::CREATED_ENVIRONMENT][] = ['databasePopulate'];

    return $events;
  }

  /**
   * Populate the database for an environment.
   *
   * I.e. after its been created, using the project default sql dump.
   *
   * @param \Drupal\shp_orchestration\Event\OrchestrationEnvironmentEvent $event
   *   Orchestration environment event.
   */
  public function databasePopulate(OrchestrationEnvironmentEvent $event) {
    $orchestration_provider = $event->getOrchestrationProvider();

    $project = $event->getProject();
    $site = $event->getSite();
    $environment = $event->getEnvironment();

    $populate_command = str_replace(
      ["\r\n", "\n", "\r"], ' && ', trim($this->config->get('populate_command'))
    );
    $populate_command = $this->token->replace($populate_command, ['project' => $project]);

    if (!empty($project->field_shp_default_sql->target_id)) {
      $orchestration_provider->executeJob(
        $project->title->value,
        $site->field_shp_short_name->value,
        $environment->id(),
        $environment->field_shp_git_reference->value,
        $populate_command
      );
    }
  }

}
