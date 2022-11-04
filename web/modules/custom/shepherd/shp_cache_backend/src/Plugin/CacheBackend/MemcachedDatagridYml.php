<?php

namespace Drupal\shp_cache_backend\Plugin\CacheBackend;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\node\NodeInterface;
use Drupal\shp_cache_backend\Plugin\CacheBackendBase;
use Drupal\shp_custom\Service\EnvironmentType;
use Drupal\shp_orchestration\Plugin\OrchestrationProvider\OpenShiftOrchestrationProvider;
use Drupal\shp_orchestration\TokenNamespaceTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;
use UniversityOfAdelaide\OpenShift\Client;
use UniversityOfAdelaide\OpenShift\Objects\ConfigMap;
use UniversityOfAdelaide\OpenShift\Objects\NetworkPolicy;

/**
 * Provides memcache yml config integration.
 *
 * @CacheBackend(
 *   id = "memcached_datagrid_yml",
 *   label = @Translation("Memcached Datagrid Yml")
 * )
 */
class MemcachedDatagridYml extends CacheBackendBase {

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
   * The JGD config file.
   *
   * @var string
   *
   * @todo make this name configurable?
   */
  protected $jdgConfigFile = 'infinispan.yml';

  /**
   * The config map name.
   *
   * @var string
   *
   * @todo make this name configurable?
   */
  protected $configMapName = 'data-grid-configuration';

  /**
   * The default port to create a memcache instance for.
   *
   * @var int
   */
  protected $defaultPort = 11311;

  /**
   * The pod selector used for datagrid.
   *
   * @var string
   *
   * @todo make this name configurable?
   */
  protected $datagridSelector = 'data-grid';

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
  public function getEnvironmentVariables(NodeInterface $environment) {
    $deployment_name = OpenShiftOrchestrationProvider::generateDeploymentName($environment->id());
    // Use the JDG server for promoted environments, otherwise use the
    // memcached pod.
    if ($this->environmentType->isPromotedEnvironment($environment)) {
      $host = sprintf(
        '%s.%s.svc.cluster.local',
        self::getMemcacheServiceName($deployment_name),
        $this->cacheConfig->get('namespace')
      );
    }
    else {
      $host = self::getMemcachedDeploymentName($environment);
    }
    return [
      'MEMCACHE_ENABLED' => '1',
      'MEMCACHE_HOST' => $host,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onEnvironmentPromote(NodeInterface $environment) {
    $this->client->setToken($this->config->get('connection.token'));
    /** @var \Drupal\node\Entity\Node $environment */
    $this->client->setNamespace($this->buildProjectName($environment->field_shp_site->entity->id()));

    $memcached_name = self::getMemcachedDeploymentName($environment);
    // Scale the memcached deployment to 0 when the environment is promoted.
    if ($deployment_config = $this->client->getDeploymentConfig($memcached_name)) {
      $this->client->updateDeploymentConfig($memcached_name, $deployment_config, [
        'spec' => ['replicas' => 0],
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onEnvironmentDemotion(NodeInterface $environment) {
    $this->client->setToken($this->config->get('connection.token'));
    /** @var \Drupal\node\Entity\Node $environment */
    $this->client->setNamespace($this->buildProjectName($environment->field_shp_site->entity->id()));

    $memcached_name = self::getMemcachedDeploymentName($environment);
    // Scale the memcached deployment to 1 when the environment is demoted.
    if ($deployment_config = $this->client->getDeploymentConfig($memcached_name)) {
      $this->client->updateDeploymentConfig($memcached_name, $deployment_config, [
        'spec' => ['replicas' => 1],
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onEnvironmentCreate(NodeInterface $environment) {
    // Data Grid is is its own project, but uses shepherd token.
    $this->client->setToken($this->config->get('connection.token'));
    $this->client->setNamespace($this->cacheConfig->get('namespace'));

    $deployment_name = OpenShiftOrchestrationProvider::generateDeploymentName($environment->id());
    $project_name = $this->buildProjectName($environment->field_shp_site->entity->id());
    if (!$configMap = $this->client->getConfigmap($this->configMapName)) {
      return;
    }
    if (!$yml = $this->loadYml($configMap)) {
      return;
    }

    $name = self::getMemcacheName($environment);
    // Find the next port to use.
    $max_port = $this->defaultPort;
    foreach ($yml['infinispan']['server']['socketBindings']['socketBinding'] as $binding) {
      $binding_name = $binding['name'];
      $port = (int) $binding['port'];
      if (strpos($binding_name, 'memcached_node') === 0 && !empty($port) && $port > $max_port) {
        $max_port = $port;
      }
    }
    $new_port = $max_port + 1;

    // Create the NetworkPolicy.
    $network_policy = NetworkPolicy::create()
      ->setIngressPodMatchLabels(['app' => $deployment_name])
      ->setIngressNamespaceMatchLabels(['kubernetes.io/metadata.name' => $project_name])
      ->setPodSelectorMatchLabels(['clusterName' => $this->datagridSelector])
      ->setPort($new_port)
      ->setName(self::getNetworkPolicyName($deployment_name));
    $this->client->createNetworkpolicy($network_policy);

    // Create the Service.
    $service_name = self::getMemcacheServiceName($deployment_name);
    $this->client->createService(
      $service_name,
      $this->datagridSelector,
      11211,
      $new_port,
      $deployment_name,
      ['clusterName' => $this->datagridSelector]
    );

    // Add the socket-binding.
    $socket_binding = [
      'name' => $name,
      'port' => $new_port,
    ];

    // Add the memcache-connector element.
    $service_config = [
      'memcachedConnector' => [
        'cache' => $name,
        'name' => $name,
        'socketBinding' => $name,
      ],
    ];

    $distributed_cache_config = [
      'distributedCache' => [
        'mode' => 'SYNC',
        'start' => 'EAGER',
        'statistics' => TRUE,
        'encoding' => [
          'mediaType' => 'text/plain; charset=UTF-8',
        ],
        'memory' => [
          'maxSize' => '100MB',
          'whenFull' => 'REMOVE',
        ],
      ],
    ];

    $yml['infinispan']['cacheContainer']['caches'][$name] = $distributed_cache_config;

    // Locate the cluster of memcache connectors.
    $index = 0;
    foreach ($yml['infinispan']['server']['endpoints'] as $index => $connectors) {
      if (isset($connectors['memcachedConnector'])) {
        break;
      }
    }

    // If this is the first one, add in the section.
    if (!$index) {
      $yml['infinispan']['server']['endpoints'][] = [
        'connectors' => [
          'rest' => [
            'restConnector' => [
              'authentication' => [
                'mechanisms' => 'BASIC',
              ],
            ],
          ],
          $service_name => $service_config,
        ],
        'securityRealm' => 'default',
        'socketBinding' => 'default',
      ];
    }
    else {
      // Otherwise, just add the new service.
      $yml['infinispan']['server']['endpoints'][$index]['connectors'][$service_name] = $service_config;
    }

    $yml['infinispan']['server']['socketBindings']['socketBinding'][] = $socket_binding;

    $yaml = new Dumper(2);
    $dumpedYaml = $yaml->dump($yml, PHP_INT_MAX, 0, SymfonyYaml::DUMP_EXCEPTION_ON_INVALID_TYPE | SymfonyYaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

    // Fixup random things that look different to what Data Grid wants.
    $dumpedYaml = preg_replace('/\-\n\s+/', '- ', $dumpedYaml);
    $dumpedYaml = str_replace('authorization: {  }', 'authorization: {}', $dumpedYaml);

    $configMap->setDataKey($this->jdgConfigFile, $dumpedYaml);
    $this->client->updateConfigmap($configMap);

    // Update the stateful set, adding the port definition for this service.
    if (!$statefulSet = $this->client->getStatefulset($this->datagridSelector)) {
      return;
    }
    $spec = $statefulSet->getSpec();
    foreach ($spec['template']['spec']['containers'] as &$container) {
      $container['ports'][] = [
        'containerPort' => $new_port,
        'name' => $service_name,
        'protocol' => 'TCP',
      ];
    }
    $statefulSet->setSpec($spec);
    $this->client->updateStatefulset($statefulSet);

    // Do this last as it needs to switch the client to the sites project.
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
    // Image streams are in the shepherd project.
    $shepherdNamespace = $this->config->get('connection.namespace');
    $this->client->setToken($this->config->get('connection.token'));
    $this->client->setNamespace($shepherdNamespace);

    if (!$image_stream = $this->client->getImageStream('memcached')) {
      $this->client->createImageStream($this->generateImageStream());
    }

    $this->client->setToken($this->getSiteToken($environment->field_shp_site->entity->id()));
    $this->client->setNamespace($this->buildProjectName($environment->field_shp_site->entity->id()));

    $memcachedDeploymentName = self::getMemcachedDeploymentName($environment);
    $deploymentName = OpenShiftOrchestrationProvider::generateDeploymentName($environment->id());
    $memcachedPort = 11211;

    $data = $this->formatMemcachedDeployData($deploymentName, $environment->field_shp_site->target_id, $environment->id());
    $deployConfig = $this->generateDeploymentConfig(
      $environment,
      $memcachedDeploymentName,
      $memcachedPort,
      'memcached:alpine',
      $shepherdNamespace,
      $data
    );
    $this->client->createDeploymentConfig($deployConfig);

    $this->client->createService($memcachedDeploymentName, $memcachedDeploymentName, $memcachedPort, $memcachedPort, $deploymentName);
  }

  /**
   * Generate image stream.
   *
   * @return array
   *   The image stream.
   */
  protected function generateImageStream() {
    return [
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
  }

  /**
   * Format the memcache deploy data.
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
    return [
      'labels' => [
        'site_id' => (string) $site_id,
        'environment_id' => (string) $environment_id,
        'app' => $name,
        'deploymentconfig' => $name,
      ],
    ];
  }

  /**
   * Generate deployment config.
   *
   * @param \Drupal\node\NodeInterface $environment
   *   The environment.
   * @param string $memcached_name
   *   Memcache name.
   * @param string $memcached_port
   *   Memcache port.
   * @param string $image_stream_tag
   *   The image stream tag to use.
   * @param string $image_stream_namespace
   *   The namespace to load the image stream from.
   * @param array $data
   *   Array of data for labels.
   *
   * @return array
   *   The deployment config array.
   */
  protected function generateDeploymentConfig(NodeInterface $environment, string $memcached_name, string $memcached_port, string $image_stream_tag, string $image_stream_namespace, array $data) {
    $config = [
      'apiVersion' => 'apps.openshift.io/v1',
      'kind' => 'DeploymentConfig',
      'metadata' => [
        'name' => $memcached_name,
        'labels' => array_key_exists('labels', $data) ? $data['labels'] : [],
      ],
      'spec' => [
        'replicas' => $this->environmentType->isPromotedEnvironment($environment) ? 0 : 1,
        'revisionHistoryLimit' => 1,
        'selector' => array_key_exists('labels', $data) ? array_merge($data['labels'], ['name' => $memcached_name]) : [],
        'strategy' => [
          'type' => 'Rolling',
          'resources' => [
            'limits' => [
              'cpu' => '100m',
              'memory' => '128Mi',
            ],
            'requests' => [
              'cpu' => '50m',
              'memory' => '64Mi',
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
                  '-m 64',
                ],
                'ports' => [
                  [
                    'name' => 'memcache',
                    'containerPort' => (int) $memcached_port,
                  ],
                ],
                'resources' => [
                  'limits' => [
                    'cpu' => '100m',
                    'memory' => '128Mi',
                  ],
                  'requests' => [
                    'cpu' => '50m',
                    'memory' => '64Mi',
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
                'name' => $image_stream_tag,
                'namespace' => $image_stream_namespace,
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
   * {@inheritdoc}
   */
  public function onEnvironmentDelete(NodeInterface $environment) {
    $this->client->setToken($this->config->get('connection.token'));
    $this->client->setNamespace($this->cacheConfig->get('namespace'));

    $deployment_name = OpenShiftOrchestrationProvider::generateDeploymentName($environment->id());
    $np_name = self::getNetworkPolicyName($deployment_name);
    if ($this->client->getNetworkpolicy($np_name)) {
      $this->client->deleteNetworkpolicy($np_name);
    }
    $service_name = self::getMemcacheServiceName($deployment_name);
    if ($this->client->getService($service_name)) {
      $this->client->deleteService($service_name);
    }

    if (!$configMap = $this->client->getConfigmap($this->configMapName)) {
      return;
    }
    if (!$yml = $this->loadYml($configMap)) {
      return;
    }
    $name = self::getMemcacheName($environment);

    unset($yml['infinispan']['cacheContainer']['caches'][$name]);

    foreach ($yml['infinispan']['server']['endpoints'] as $index => &$connector) {
      if (isset($connector['connectors'][$service_name]['memcachedConnector']['name'])
      && $connector['connectors'][$service_name]['memcachedConnector']['name'] == $name) {
        unset($connector['connectors'][$service_name]);
      }
    }

    foreach ($yml['infinispan']['server']['socketBindings']['socketBinding'] as $index => $binding) {
      if ($binding['name'] == $name) {
        unset($yml['infinispan']['server']['socketBindings']['socketBinding'][$index]);
      }
    }

    // Reindex array to avoid numeric indexes appearing in yaml.
    $yml['infinispan']['server']['socketBindings']['socketBinding'] = array_values($yml['infinispan']['server']['socketBindings']['socketBinding']);

    $yaml = new Dumper(2);
    $dumpedYaml = $yaml->dump($yml, PHP_INT_MAX, 0, SymfonyYaml::DUMP_EXCEPTION_ON_INVALID_TYPE | SymfonyYaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

    // Fixup random things that look different to what Data Grid wants.
    $dumpedYaml = preg_replace('/\-\n\s+/', '- ', $dumpedYaml);
    $dumpedYaml = str_replace('authorization: {  }', 'authorization: {}', $dumpedYaml);

    $configMap->setDataKey($this->jdgConfigFile, $dumpedYaml);
    $this->client->updateConfigmap($configMap);

    // Update the stateful set, removing the port definition for this service.
    if (!$statefulSet = $this->client->getStatefulset($this->datagridSelector)) {
      return;
    }
    $spec = $statefulSet->getSpec();
    foreach ($spec['template']['spec']['containers'] as &$container) {
      foreach ($container['ports'] as $pid => $port) {
        if ($port['name'] === $service_name) {
          unset($container['ports'][$pid]);
        }
      }
      // Remove the empty ports key so OS doesn't complain about empty arrays.
      if (empty($container['ports'])) {
        unset($container['ports']);
      }
      else {
        // Reindex the array.
        $container['ports'] = array_values($container['ports']);
      }
    }

    $statefulSet->setSpec($spec);
    $this->client->updateStatefulset($statefulSet);

    // Delete the memcached deployment from the sites project.
    /** @var \Drupal\node\Entity\Node $environment */
    $site = $environment->field_shp_site->entity->id();
    $this->client->setToken($this->getSiteToken($site));
    $this->client->setNamespace($this->buildProjectName($site));
    $memcached_name = self::getMemcachedDeploymentName($environment);
    if ($this->client->getService($memcached_name)) {
      $this->client->deleteService($memcached_name);
    }
    if ($this->client->getDeploymentConfig($memcached_name)) {
      $this->client->deleteDeploymentConfig($memcached_name);
    }
    $this->client->deleteReplicationControllers('', 'openshift.io/deployment-config.name=' . $memcached_name);
  }

  /**
   * Loads the YML from the datagrid config map.
   *
   * @param \UniversityOfAdelaide\OpenShift\Objects\ConfigMap $configMap
   *   The config map.
   *
   * @return array|bool
   *   An array of yml structure, or FALSE on failure.
   */
  protected function loadYml(ConfigMap $configMap) {
    if (empty($configMap->getData()[$this->jdgConfigFile])) {
      return FALSE;
    }
    if (!$yml = Yaml::parse($configMap->getData()[$this->jdgConfigFile])) {
      return FALSE;
    }
    return $yml;
  }

  /**
   * Gets the memcache name for this environment.
   *
   * @param \Drupal\node\NodeInterface $environment
   *   The environment name.
   *
   * @return string
   *   The memcache name.
   */
  protected static function getMemcacheName(NodeInterface $environment) {
    return 'memcached_node-' . $environment->id();
  }

  /**
   * Get the network policy name.
   *
   * @param string $deployment_name
   *   The deployment name.
   *
   * @return string
   *   The network policy name.
   */
  protected static function getNetworkPolicyName(string $deployment_name) {
    return 'datagrid-allow-' . $deployment_name;
  }

  /**
   * Gets the memcache service name.
   *
   * @param string $deployment_name
   *   The deployment name.
   *
   * @return string
   *   The service name.
   */
  protected static function getMemcacheServiceName(string $deployment_name) {
    return $deployment_name . '-mc';
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
