<?php

namespace Drupal\shp_redis_support\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\shp_orchestration\Event\OrchestrationEvents;
use Drupal\shp_orchestration\Event\OrchestrationEnvironmentEvent;

class DeploymentEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[OrchestrationEvents::SETUP_ENVIRONMENT][]   = array('setupRedisDeployment');
    $events[OrchestrationEvents::CREATED_ENVIRONMENT][] = array('createRedisDeployment');
    $events[OrchestrationEvents::DELETED_ENVIRONMENT][] = array('deleteRedisDeployment');

    return $events;
  }

  public function setupRedisDeployment(OrchestrationEnvironmentEvent $event) {
    $event->setEnvironmentVariables([
      'REDIS_ENABLED' => '1',
      'REDIS_HOST' => $event->getDeploymentName() . '-redis',
    ]);
  }

  /**
   * Add a redis pod to an existing environment deployment.
   *
   * @param \Drupal\shp_orchestration\Event\OrchestrationEnvironmentEvent $event
   *
   */
  public function createRedisDeployment(OrchestrationEnvironmentEvent $event) {
    $orchestration_provider = $event->getOrchestrationProvider();
    if ($orchestration_provider->getPluginId() == 'openshift_with_redis') {
      $deployment_name = $event->getDeploymentName();
      $orchestration_provider->createRedisDeployment($deployment_name);
    }
  }

  /**
   * Add a redis pod to an existing environment deployment.
   *
   * @param \Drupal\shp_orchestration\Event\OrchestrationEnvironmentEvent $event
   *
   */
  public function deleteRedisDeployment(OrchestrationEnvironmentEvent $event) {
    $orchestration_provider = $event->getOrchestrationProvider();
    if ($orchestration_provider->getPluginId() == 'openshift_with_redis') {
      $deployment_name = $event->getDeploymentName();
      $orchestration_provider->deleteRedisDeployment($deployment_name);
    }
  }

}
