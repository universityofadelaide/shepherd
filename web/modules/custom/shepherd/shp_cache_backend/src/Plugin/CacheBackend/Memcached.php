<?php

namespace Drupal\shp_cache_backend\Plugin\CacheBackend;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\node\NodeInterface;
use Drupal\shp_cache_backend\Plugin\CacheBackendBase;
use Drupal\shp_custom\Service\EnvironmentType;
use Drupal\shp_orchestration\Plugin\OrchestrationProvider\OpenShiftOrchestrationProvider;
use Drupal\shp_orchestration\TokenNamespaceTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UniversityOfAdelaide\OpenShift\Client;

/**
 * Provides Memcached integration.
 *
 * @CacheBackend(
 *   id = "memcached",
 *   label = @Translation("Memcached")
 * )
 */
class Memcached extends CacheBackendBase {

  use TokenNamespaceTrait;

  /**
   * The configuration for the client.
   *
   * @var string
   */
  protected $config;

  /**
   * The configuration for the cache plugin client.
   *
   * @var string
   */
  protected $cacheConfig;

  /**
   * The namespace objects are being created in.
   *
   * @var string
   */
  protected $namespace;

  /**
   * Environment type service.
   *
   * @var \Drupal\shp_custom\Service\EnvironmentTypeInterface
   */
  protected $environmentType;

  /**
   * Memcache constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \UniversityOfAdelaide\OpenShift\Client $client
   *   The OS Client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The orchestration config.
   * @param \Drupal\shp_custom\Service\EnvironmentType $environmentType
   *   Environment type.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Client $client, ConfigFactoryInterface $config, EnvironmentType $environmentType) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $client);
    $this->environmentType = $environmentType;
    $this->config = $config->get('shp_orchestration.settings');
    $this->cacheConfig = $config->get('shp_cache_backend.settings');
    $this->namespace = $this->config->get('connection.namespace');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('shp_orchestration.client'),
      $container->get('config.factory'),
      $container->get('shp_custom.environment_type')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironmentVariables(NodeInterface $environment): array {
    $deploymentName = OpenShiftOrchestrationProvider::generateDeploymentName($environment->id());
    return [
      'MEMCACHE_ENABLED' => '1',
      'MEMCACHE_HOST' => $deploymentName . '-memcached',
      'MEMCACHE_PREFIX' => $deploymentName,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onEnvironmentCreate(NodeInterface $environment) {
    $this->generateMemcachedDeployment($environment);
  }

  /**
   * Generate a memcached deployment for non-prod environments.
   *
   * @param \Drupal\node\NodeInterface $environment
   *   The environment.
   */
  protected function generateMemcachedDeployment(NodeInterface $environment) {
    /** @var \Drupal\node\Entity\Node $environment */
    $this->client->setToken($this->getSiteToken($environment->field_shp_site->entity->id()));
    $this->client->setNamespace($this->buildProjectName($environment->field_shp_site->entity->id()));

    $memcachedDeploymentName = self::getMemcachedDeploymentName($environment);
    $deploymentName = OpenShiftOrchestrationProvider::generateDeploymentName($environment->id());
    $memcachedPort = 11211;

    if (!$image_stream = $this->client->getImageStream('memcached')) {
      $this->client->createImageStream($this->generateImageStream());
    }

    $data = $this->formatMemcachedDeployData($deploymentName, $environment->field_shp_site->target_id, $environment->id());
    $deployConfig = $this->generateDeploymentConfig($memcachedDeploymentName, $memcachedPort, $data);
    $this->client->createDeploymentConfig($deployConfig);

    $this->client->createService($memcachedDeploymentName, $memcachedDeploymentName, $memcachedPort, $memcachedPort, $deploymentName);
  }

  /**
   * {@inheritdoc}
   */
  public function onEnvironmentDelete(NodeInterface $environment) {
    $deployment_name = self::getMemcachedDeploymentName($environment);
    $memcached_name = $deployment_name . '-memcached';
    if ($this->client->getService($memcached_name)) {
      $this->client->deleteService($memcached_name);
    }
    if ($this->client->getDeploymentConfig($memcached_name)) {
      $this->client->deleteDeploymentConfig($memcached_name);
    }
    $this->client->deleteReplicationControllers('', 'openshift.io/deployment-config.name=' . $memcached_name);
  }

  /**
   * Format the memcached deploy data.
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
  protected function formatMemcachedDeployData(string $name, int $site_id, int $environment_id) {
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
      'apiVersion' => 'image.openshift.io/v1',
      'kind' => 'ImageStream',
      'metadata' => [
        'name' => 'memcached',
        'annotations' => [
          'description' => 'Track the memcached alpine image',
        ],
      ],
      'spec' => [
        'lookupPolicy' => [
          'local' => FALSE,
        ],
        'tags' => [
          [
            'annotations' => [
              'openshift.io/imported-from' => 'docker.io/memcached:alpine',
            ],
            'from' => [
              'kind' => 'DockerImage',
              'name' => 'docker.io/memcached:alpine',
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
   * @param string $memcached_name
   *   Memcached name.
   * @param string $memcached_port
   *   Memcached port.
   * @param array $data
   *   Array of data for labels.
   *
   * @return array
   *   Deployment config definition.
   */
  protected function generateDeploymentConfig(string $memcached_name, string $memcached_port, array $data) {
    $config = [
      'apiVersion' => 'apps.openshift.io/v1',
      'kind' => 'DeploymentConfig',
      'metadata' => [
        'name' => $memcached_name,
        'labels' => array_key_exists('labels', $data) ? $data['labels'] : [],
      ],
      'spec' => [
        'replicas' => 1,
        'revisionHistoryLimit' => 1,
        'selector' => array_key_exists('labels', $data) ? array_merge($data['labels'], ['name' => $memcached_name]) : [],
        'strategy' => [
          'type' => 'Rolling',
          'resources' => [
            'limits' => [
              'cpu' => '200m',
              'memory' => '256Mi',
            ],
            'requests' => [
              'cpu' => '100m',
              'memory' => '50Mi',
            ],
          ],
        ],
        'template' => [
          'metadata' => [
            'annotations' => [
              'openshift.io/generated-by' => 'shp_memcached_support',
            ],
            'labels' => array_key_exists('labels', $data) ? array_merge($data['labels'], ['name' => $memcached_name]) : [],
          ],
          'spec' => [
            'containers' => [
              [
                'image' => 'docker.io/memcached:alpine',
                'name' => $memcached_name,
                'livenessProbe' => [
                  'initialDelaySeconds' => 30,
                  'timeoutSeconds' => 5,
                  'tcpSocket' => [
                    'port' => 'memcache',
                  ],
                ],
                'readinessProbe' => [
                  'initialDelaySeconds' => 5,
                  'timeoutSeconds' => 1,
                  'tcpSocket' => [
                    'port' => 'memcache',
                  ],
                ],
                'command' => [
                  'memcached',
                  '-m 128',
                ],
                'ports' => [
                  [
                    'name' => 'memcache',
                    'containerPort' => (int) $memcached_port,
                  ],
                ],
                'resources' => [
                  'limits' => [
                    'cpu' => '200m',
                    'memory' => '256Mi',
                  ],
                  'requests' => [
                    'cpu' => '100m',
                    'memory' => '128Mi',
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
                $memcached_name,
              ],
              'from' => [
                'kind' => 'ImageStreamTag',
                'name' => 'memcached:alpine',
              ],
            ],
            'type' => 'ImageChange',
          ],
        ],
      ],
    ];
    return $config;
  }

  /**
   * Get the memcached deployment name for an environment.
   *
   * @param \Drupal\node\NodeInterface $environment
   *   The environment.
   *
   * @return string
   *   The deployment name.
   */
  protected static function getMemcachedDeploymentName(NodeInterface $environment) {
    return sprintf('%s-memcached', OpenShiftOrchestrationProvider::generateDeploymentName($environment->id()));
  }

}
