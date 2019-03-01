<?php

namespace Drupal\shp_redis_support\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\shp_orchestration\Event\OrchestrationEvents;
use Drupal\shp_orchestration\Event\OrchestrationEnvironmentEvent;

/**
 * Class DeploymentEventSubscriber.
 */
class DeploymentEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[OrchestrationEvents::CREATED_ENVIRONMENT][] = array('createRedisDeployment');
    $events[OrchestrationEvents::DELETED_ENVIRONMENT][] = array('deleteRedisDeployment');

    return $events;
  }

  /**
   * Add a redis pod to an existing environment deployment.
   *
   * @param \Drupal\shp_orchestration\Event\OrchestrationEnvironmentEvent $event
   *   Orchestration environment event.
   */
  public function createRedisDeployment(OrchestrationEnvironmentEvent $event) {
    $orchestration_provider = $event->getOrchestrationProvider();
    if ($orchestration_provider->getPluginId() === 'openshift_with_redis') {
      $deployment_name = $event->getDeploymentName();
      $site_id = $event->getSite()->id();
      $environment = $event->getEnvironment()->id();
      $orchestration_provider->createRedisDeployment($deployment_name, $site_id, $environment);
    }
  }

  /**
   * Add a redis pod to an existing environment deployment.
   *
   * @param \Drupal\shp_orchestration\Event\OrchestrationEnvironmentEvent $event
   *   Orchestration environment event.
   */
  public function deleteRedisDeployment(OrchestrationEnvironmentEvent $event) {
    $orchestration_provider = $event->getOrchestrationProvider();
    if ($orchestration_provider->getPluginId() === 'openshift_with_redis') {
      $deployment_name = $event->getDeploymentName();
      $orchestration_provider->deleteRedisDeployment($deployment_name);
    }
  }

}
