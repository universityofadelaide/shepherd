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
   * @param string $deployment_name
   *   The deployment name.
   * @param string $site_id
   *   The ID of the site the redis instance will be working with.
   * @param string $environment
   *   The ID of the environment the redis instance will be working with.
   *
   * @throws \UniversityOfAdelaide\OpenShift\ClientException
   */
  public function createRedisDeployment(string $deployment_name, string $site_id, string $environment) {
    $redis_name = $deployment_name . '-redis';
    $redis_port = 6379;

    $image_stream = $this->client->getImageStream('redis');
    if (!$image_stream) {
      $image_stream = $this->generateImageStream();
      $this->client->createImageStream($image_stream);
    }

    $data = $this->formatRedisDeployData($deployment_name, $site_id, $environment);
    $redis_deployment_config = $this->generateDeploymentConfig($deployment_name, $redis_name, $redis_port, $data);
    $this->client->createDeploymentConfig($redis_deployment_config);

    $this->client->createService($redis_name, $redis_name, $redis_port,
      $redis_port, $deployment_name);
  }

  /**
   * Delete the redis deployment.
   *
   * @param string $deployment_name
   *   The deployment name.
   *
   * @throws \UniversityOfAdelaide\OpenShift\ClientException
   */
  public function deleteRedisDeployment(string $deployment_name) {
    $redis_name = $deployment_name . '-redis';
    if ($this->client->getService($redis_name)) {
      $this->client->deleteService($redis_name);
    }
    if ($this->client->getDeploymentConfig($redis_name)) {
      $this->client->deleteDeploymentConfig($redis_name);
    }
    $this->client->deleteReplicationControllers('', 'openshift.io/deployment-config.name=' . $redis_name);
  }

  /**
   * Formats redis deploy data.
   *
   * @param string $name
   *   The name of the deployment config.
   * @param int $site_id
   *   The ID of the site the environment represents.
   * @param int $environment_id
   *   The ID of the environment being created.
   *
   * @return array
   *   The deployment config array.
   */
  private function formatRedisDeployData(string $name, int $site_id, int $environment_id) {
    $deploy_data = [
      'labels' => [
        'site_id' => (string) $site_id,
        'environment_id' => (string) $environment_id,
        'app' => $name,
        'deploymentconfig' => $name,
      ],
    ];

    return $deploy_data;
  }

  /**
   * Generate image stream.
   *
   * @return array
   *   Image stream definition.
   */
  public function generateImageStream() {
    $image_stream = [
      'apiVersion' => 'v1',
      'kind' => 'ImageStream',
      'metadata' => [
        'name' => 'redis',
        'annotations' => [
          'description' => 'Track the redis alpine image',
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
            'name' => 'alpine',
            'referencePolicy' => [
              'type' => 'Source',
            ],
          ],
        ],
      ],
    ];
    return $image_stream;
  }

  /**
   * Generate deployment config.
   *
   * @param string $deployment_name
   *   Deployment name.
   * @param string $redis_name
   *   Redis name.
   * @param string $redis_port
   *   Redis port.
   * @param array $data
   *   Array of data for labels.
   *
   * @return array
   *   Deployment config definition.
   */
  public function generateDeploymentConfig(string $deployment_name, string $redis_name, string $redis_port, array $data) {
    $redis_data = $deployment_name . '-data';
    $redis_conf = $deployment_name . '-config';

    $redis_deployment_config = [
      'apiVersion' => 'v1',
      'kind' => 'DeploymentConfig',
      'metadata' => [
        'name' => $redis_name,
        'labels' => array_key_exists('labels', $data) ? $data['labels'] : [],
      ],
      'spec' => [
        'replicas' => 1,
        'selector' => array_key_exists('labels', $data) ?
        array_merge($data['labels'], ['name' => $redis_name]) : [],
        'strategy' => [
          'type' => 'Rolling',
        ],
        'template' => [
          'metadata' => [
            'annotations' => [
              'openshift.io/generated-by' => 'shp_redis_support',
            ],
            'labels' => array_key_exists('labels', $data) ?
            array_merge($data['labels'], ['name' => $redis_name]) : [],
          ],
          'spec' =>
            [
              'containers' =>
                [
                  [
                    'image' => 'docker.io/redis:alpine',
                    'name' => $redis_name,
                    'livenessProbe' => [
                      'initialDelaySeconds' => 30,
                      'tcpSocket' => [
                        'port' => 6379,
                      ],
                    ],
                    'command' => [
                      '/usr/local/bin/docker-entrypoint.sh',
                      '/usr/local/etc/redis/redis.conf',
                    ],
                    'ports' => [
                      [
                        'containerPort' => $redis_port,
                      ],
                    ],
                    'readinessProbe' => [
                      'exec' => [
                        'command' => [
                          '/bin/sh',
                          '-i',
                          '-c',
                          'test "$(redis-cli ping)" == "PONG"',
                        ],
                      ],
                    ],
                    'resources' => [
                      'limits' =>
                        [
                          'cpu' => '200m',
                          'memory' => '256Mi',
                        ],
                      'requests' =>
                        [
                          'cpu' => '100m',
                          'memory' => '50Mi',
                        ],
                    ],
                    'volumeMounts' => [
                      [
                        'mountPath' => '/data',
                        'name' => $redis_data,
                      ],
                      [
                        'mountPath' => '/usr/local/etc/redis',
                        'name' => $redis_conf,
                      ],

                    ],
                  ],
                ],
              'volumes' => [
                [
                  'name' => $redis_data,
                ],
                [
                  'name' => $redis_conf,
                  'configMap' => [
                    'name' => 'redis-config',
                    'items' => [
                      [
                        'key' => 'redis-config',
                        'path' => 'redis.conf',
                      ],
                    ],
                  ],
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
    return $redis_deployment_config;
  }

}
