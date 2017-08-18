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
    $events[OrchestrationEvents::CREATED_ENVIRONMENT][] = array('createRedisDeployment');
    $events[OrchestrationEvents::DELETED_ENVIRONMENT][] = array('deleteRedisDeployment');

    return $events;
  }

  /**
   * Add a redis pod to an existing environment deployment.
   *
   * @param \Drupal\shp_orchestration\Event\OrchestrationEnvironmentEvent $event
   *
   */
  public function createRedisDeployment(OrchestrationEnvironmentEvent $event) {
    $client = $event->getClient();
    $deployment_config = $event->getDeploymentConfig();

    $app_name = $deployment_config['metadata']['name'];
    $redis_name = $app_name . '-redis';
    $redis_data = $redis_name . '-data';

    $image_stream = $client->getImageStream('redis');
    if (!$image_stream) {
      $image_stream = [
        'apiVersion' => 'v1',
        'kind'       => 'ImageStream',
        'metadata'   => [
          'name'        => 'redis',
          'annotations' => [
            'description' => 'Keeps track of changes in the application image',
          ],
          'labels'      => [
            'app' => 'redis',
          ],
        ],
        'spec'       => [
          'lookupPolicy' => [
            'local' => FALSE,
          ],
          'tags'         => [
            [
              'annotations'     => [
                'openshift.io/imported-from' => 'docker.io/redis:alpine'
              ],
              'from'            => [
                'kind' => 'DockerImage',
                'name' => 'docker.io/redis:alpine',
              ],
              'importPolicy'    => [],
              'name'            => 'alpine',
              'referencePolicy' => [
                'type' => 'Source',
              ],
            ],
          ],
        ],
      ];
      $client->createImageStream($image_stream);
    }

    $redis_deployment_config = [
      'apiVersion' => 'v1',
      'kind'       => 'DeploymentConfig',
      'metadata'   => [
        'name'   => $redis_name,
        'labels' => [
          'app' => $app_name,
        ],
      ],
      'spec'       => [
        'replicas' => 1,
        'selector' => [
          'app'              => $app_name,
          'deploymentconfig' => $redis_name,
        ],
        'strategy'         => [
          'type' => 'Rolling',
        ],
        'template' => [
          'metadata' => [
            'annotations' => [
              'openshift.io/generated-by' => 'shp_redis_support',
            ],
            'labels'      => [
              'app'              => $app_name,
              'deploymentconfig' => $redis_name,
            ],
          ],
          'spec'     =>
            [
              'containers' =>
                [
                  [
                    'image'        => 'docker.io/redis:alpine',
                    'name'         => $redis_name,
                    'ports'        => [
                      [
                        'containerPort' => 6379,
                      ],
                    ],
                    'resources'    => [],
                    'volumeMounts' => [
                      [
                        'mountPath' => '/data',
                        'name'      => $redis_data,
                      ],
                    ],
                  ],
                ],
              'volumes' => [
                [
                  'emptyDir' => [],
                  'name' => $redis_data,
                ],
              ],
            ],
        ],
        'triggers' => [
          [
            'imageChangeParams' => [
              'automatic'      => TRUE,
              'containerNames' => [
                $redis_name
              ],
              'from'           => [
                'kind' => 'ImageStreamTag',
                'name' => 'redis:alpine',
              ],
            ],
            'type'              => 'ImageChange',
          ],
        ],
      ],
    ];

    $client->createDeploymentConfig($redis_deployment_config);

    $service_data = [
      'port' => 6739,
      'targetPort' => 6739,
      'deployment' => $redis_name,
    ];
    $client->createService($redis_name, $service_data);
  }

  /**
   * Add a redis pod to an existing environment deployment.
   *
   * @param \Drupal\shp_orchestration\Event\OrchestrationEnvironmentEvent $event
   *
   */
  public function deleteRedisDeployment(OrchestrationEnvironmentEvent $event) {
    $client = $event->getClient();
    $deployment_config = $event->getDeploymentConfig();

    $app_name = $deployment_config['metadata']['name'];
    $redis_name = $app_name . '-redis';

    //$client->updateDeploymentConfig($redis_name, 0);
    //$client->updateReplicationControllers('', 'app=' . $redis_name, 0);

    $client->deleteService($redis_name);

    $client->deleteDeploymentConfig($redis_name);
    //$client->deleteReplicationControllers('', 'app=' . $redis_name);
  }

}
