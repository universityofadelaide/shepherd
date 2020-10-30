<?php

namespace Drupal\shp_cache_backend\Plugin\CacheBackend;

use Drupal\node\NodeInterface;
use Drupal\shp_cache_backend\Plugin\CacheBackendBase;
use Drupal\shp_orchestration\Plugin\OrchestrationProvider\OpenShiftOrchestrationProvider;

/**
 * Provides Redis integration.
 *
 * @CacheBackend(
 *   id = "redis",
 *   label = @Translation("Redis")
 * )
 */
class Redis extends CacheBackendBase {

  /**
   * {@inheritdoc}
   */
  public function getEnvironmentVariables(NodeInterface $environment) {
    $deployment_name = OpenShiftOrchestrationProvider::generateDeploymentName($environment->id());
    return [
      'REDIS_ENABLED' => '1',
      'REDIS_HOST' => $deployment_name . '-redis',
      'REDIS_PREFIX' => $deployment_name,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onEnvironmentCreate(NodeInterface $environment) {
    // @todo: move this function somewhere else?
    $deployment_name = OpenShiftOrchestrationProvider::generateDeploymentName($environment->id());
    $redis_name = $deployment_name . '-redis';
    $redis_port = 6379;

    if (!$image_stream = $this->client->getImageStream('redis')) {
      $image_stream = $this->generateImageStream();
      $this->client->createImageStream($image_stream);
    }

    $data = $this->formatRedisDeployData($deployment_name, $environment->field_shp_site->target_id, $environment->id());
    $redis_deployment_config = $this->generateDeploymentConfig($deployment_name, $redis_name, $redis_port, $data);
    $this->client->createDeploymentConfig($redis_deployment_config);

    $this->client->createService($redis_name, $redis_name, $redis_port, $redis_port, $deployment_name);
  }

  /**
   * {@inheritdoc}
   */
  public function onEnvironmentDelete(NodeInterface $environment) {
    $deployment_name = OpenShiftOrchestrationProvider::generateDeploymentName($environment->id());
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
   * Format the redis deploy data.
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
  protected function formatRedisDeployData(string $name, int $site_id, int $environment_id) {
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
  protected function generateImageStream() {
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
  protected function generateDeploymentConfig(string $deployment_name, string $redis_name, string $redis_port, array $data) {
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
        'selector' => array_key_exists('labels', $data) ? array_merge($data['labels'], ['name' => $redis_name]) : [],
        'strategy' => [
          'type' => 'Rolling',
        ],
        'template' => [
          'metadata' => [
            'annotations' => [
              'openshift.io/generated-by' => 'shp_redis_support',
            ],
            'labels' => array_key_exists('labels', $data) ? array_merge($data['labels'], ['name' => $redis_name]) : [],
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
