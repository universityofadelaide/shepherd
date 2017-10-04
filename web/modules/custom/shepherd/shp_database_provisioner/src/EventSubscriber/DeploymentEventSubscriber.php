<?php

namespace Drupal\shp_database_provisioner\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\shp_orchestration\Event\OrchestrationEvents;
use Drupal\shp_orchestration\Event\OrchestrationEnvironmentEvent;

class DeploymentEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[OrchestrationEvents::CREATED_ENVIRONMENT][] = array('databasePopulate');

    return $events;
  }

  /**
   * Populate the database for an environment after its been created.
   *
   * @param \Drupal\shp_orchestration\Event\OrchestrationEnvironmentEvent $event
   */
  public function databasePopulate(OrchestrationEnvironmentEvent $event) {
    $orchestration_provider = $event->getOrchestrationProvider();

    $project = $event->getProject();
    $site = $event->getSite();
    $environment = $event->getEnvironment();

    if (!empty($project->field_shp_sql_dump->target_id)) {
      $public_filename = file_create_url($project->field_shp_sql_dump->entity->getFileUri());

      $orchestration_provider->executeJob(
        $project->title->value,
        $site->field_shp_short_name->value,
        $environment->id(),
        $environment->field_shp_git_reference->value,
        "wget $public_filename -O /tmp/dump.sql; drush -r web sqlq --file=/tmp/dump.sql; drush -r web cr; rm /tmp/dump.sql"
      );
    }
  }
}
