<?php

namespace Drupal\shp_database_provisioner\EventSubscriber;

use Drupal\shp_orchestration\Event\OrchestrationEnvironmentEvent;
use Drupal\shp_orchestration\Event\OrchestrationEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class DeploymentEventSubscriber.
 */
class DeploymentEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[OrchestrationEvents::CREATED_ENVIRONMENT][] = array('databasePopulate');

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

    if (!empty($project->field_shp_default_sql->target_id)) {
      $public_filename = file_create_url($project->field_shp_default_sql->entity->getFileUri());

      $orchestration_provider->executeJob(
        $project->title->value,
        $site->field_shp_short_name->value,
        $environment->id(),
        $environment->field_shp_git_reference->value,
        "wget $public_filename -O /tmp/dump.sql; drush -r /code/web sqlq --file=/tmp/dump.sql; drush -r /code/web updb -y; robo config:import-plus; drush -r /code/web cr; rm /tmp/dump.sql"
      );
    }
  }

}
