<?php

namespace Drupal\shp_redis_support\Plugin\OrchestrationProvider;

use Drupal\shp_orchestration\Plugin\OrchestrationProvider\OpenShiftOrchestrationProvider;

/**
 * Class OpenShiftWithRedis.
 *
 * @OrchestrationProvider(
 *   id = "openshift_with_redis",
 *   name = "OpenShift with Redis",
 *   description = @Translation("OpenShift provider to perform orchestration tasks with redis support"),
 *   schema = "openshift.orchestration_provider",
 *   config_entity_id = "openshift_with_redis"
 * )
 */
class OpenShiftWithRedis extends OpenShiftOrchestrationProvider {

  /**
   * Create a redis deployment on OpenShift.
   *
   * @todo move this and the delete below into the shp_redis_support module.
   * Can we extend this class in the redis module or something?
   *
   * @param string $deployment_name
   *   The deployment name.
   */
  public function createRedisDeployment(string $deployment_name) {
    $redis_name = $deployment_name . '-redis';
    $redis_data = $deployment_name . '-data';
    $redis_port = 6379;

    $image_stream = $this->client->getImageStream('redis');
    if (!$image_stream) {
      $image_stream = [
        'apiVersion' => 'v1',
        'kind' => 'ImageStream',
        'metadata' => [
          'name' => 'redis',
          'labels' => [
            'app' => 'redis',
          ],
        ],
        'spec' => [
          'lookupPolicy' => [
            'local' => FALSE,
          ],
          'tags' => [
            [
              'annotations' => [
                'openshift.io/imported-from' => 'docker.io/redis:alpine',
              ],
              'from' => [
                'kind' => 'DockerImage',
                'name' => 'docker.io/redis:alpine',
              ],
              'importPolicy' => [],
              'name' => 'alpine',
              'referencePolicy' => [
                'type' => 'Source',
              ],
            ],
          ],
        ],
      ];
      $this->client->createImageStream($image_stream);
    }

    $redis_deployment_config = [
      'apiVersion' => 'v1',
      'kind' => 'DeploymentConfig',
      'metadata' => [
        'name' => $redis_name,
        'labels' => [
          'app' => $deployment_name,
        ],
      ],
      'spec' => [
        'replicas' => 1,
        'selector' => [
          'app' => $deployment_name,
          'deploymentconfig' => $redis_name,
        ],
        'strategy' => [
          'type' => 'Rolling',
        ],
        'template' => [
          'metadata' => [
            'annotations' => [
              'openshift.io/generated-by' => 'shp_redis_support',
            ],
            'labels' => [
              'app' => $deployment_name,
              'deploymentconfig' => $redis_name,
            ],
          ],
          'spec' =>
            [
              'containers' =>
                [
                  [
                    'image' => 'docker.io/redis:alpine',
                    'name' => $redis_name,
                    'ports' => [
                      [
                        'containerPort' => $redis_port,
                      ],
                    ],
                    'resources' => [],
                    'volumeMounts' => [
                      [
                        'mountPath' => '/data',
                        'name' => $redis_data,
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
              'automatic' => TRUE,
              'containerNames' => [
                $redis_name,
              ],
              'from' => [
                'kind' => 'ImageStreamTag',
                'name' => 'redis:alpine',
              ],
            ],
            'type' => 'ImageChange',
          ],
        ],
      ],
    ];

    $this->client->createDeploymentConfig($redis_deployment_config);
    $this->client->createService($redis_name, $redis_name, $redis_port, $redis_port, $deployment_name);
  }

  /**
   * Delete the redis deployment.
   *
   * @param string $deployment_name
   *   The deployment name.
   */
  public function deleteRedisDeployment(string $deployment_name) {
    $redis_name = $deployment_name . '-redis';
    if ($this->client->getService($redis_name)) {
      $this->client->deleteService($redis_name);
    }
    if ($this->client->getDeploymentConfig($redis_name)) {
      $this->client->deleteDeploymentConfig($redis_name);
    }
  }

}
