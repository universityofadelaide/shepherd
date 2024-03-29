<?php

namespace Drupal\shp_cache_backend\Plugin\CacheBackend;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\node\NodeInterface;
use Drupal\shp_cache_backend\Plugin\CacheBackendBase;
use Drupal\shp_custom\Service\EnvironmentType;
use Drupal\shp_orchestration\Plugin\OrchestrationProvider\OpenShiftOrchestrationProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UniversityOfAdelaide\OpenShift\Client;
use UniversityOfAdelaide\OpenShift\Objects\ConfigMap;

/**
 * Provides Redis integration.
 *
 * @CacheBackend(
 *   id = "memcached_datagrid",
 *   label = @Translation("Memcached Datagrid")
 * )
 */
class MemcachedDatagrid extends CacheBackendBase {

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
  protected $jdgConfigFile = 'standalone.xml';

  /**
   * The config map name.
   *
   * @var string
   *
   * @todo make this name configurable?
   */
  protected $configMapName = 'datagrid-config';

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
  protected $datagridSelector = 'datagrid-app';

  /**
   * Redis constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \UniversityOfAdelaide\OpenShift\Client $client
   *   The OS Client.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The orchestration config.
   * @param \Drupal\shp_custom\Service\EnvironmentType $environmentType
   *   Environment type.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Client $client, ImmutableConfig $config, EnvironmentType $environmentType) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $client);
    $this->namespace = $config->get('connection.namespace');
    $this->environmentType = $environmentType;
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
      $container->get('config.factory')->get('shp_orchestration.settings'),
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
        $this->namespace
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
    $deployment_name = OpenShiftOrchestrationProvider::generateDeploymentName($environment->id());
    if (!$configMap = $this->client->getConfigmap($this->configMapName)) {
      return;
    }
    if (!$xml = $this->loadXml($configMap)) {
      return;
    }

    // Generate the memcached deployment to be used on non-prod environments.
    $this->generateMemcachedDeployment($environment);

    $name = self::getMemcacheName($environment);
    // Find the next port to use.
    $max_port = $this->defaultPort;
    foreach ($xml->{'socket-binding-group'}->{'socket-binding'} as $binding) {
      $binding_name = (string) $binding->attributes()->name;
      $port = (int) $binding->attributes()->port;
      if (strpos($binding_name, 'memcached_node') === 0 && !empty($port) && $port > $max_port) {
        $max_port = $port;
      }
    }
    $new_port = $max_port + 1;

    // Create the Service.
    $service_name = self::getMemcacheServiceName($deployment_name);
    $this->client->createService(
      $service_name,
      $this->datagridSelector,
      11211,
      $new_port,
      $deployment_name,
      ['deploymentConfig' => $this->datagridSelector]
    );

    // Add the socket-binding.
    $socket_binding = $xml->{'socket-binding-group'}->addChild('socket-binding');
    $socket_binding->addAttribute('name', $name);
    $socket_binding->addAttribute('port', $new_port);

    // Add the memcache-connector element.
    $connector_subsystem = $xml->xpath('//c:subsystem')[0];
    $connector = $connector_subsystem->addChild('memcached-connector');
    $connector->addAttribute('name', $name);
    $connector->addAttribute('cache-container', 'clustered');
    $connector->addAttribute('cache', $name);
    $connector->addAttribute('socket-binding', $name);

    // Add the distributed-cache element.
    $cache_container = $xml->xpath('//dc:subsystem')[0]->{'cache-container'};
    $distributed_cache = $cache_container->addChild('distributed-cache');
    $distributed_cache->addAttribute('statistics', 'true');
    $distributed_cache->addAttribute('name', $name);
    $distributed_cache->addAttribute('mode', 'SYNC');
    $distributed_cache->addAttribute('start', 'EAGER');
    $distributed_cache->addChild('memory')->addChild('object');

    $configMap->setDataKey($this->jdgConfigFile, $this->formatXml($xml));
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
  }

  /**
   * Generate a memcached deployment for non-prod environments.
   *
   * @param \Drupal\node\NodeInterface $environment
   *   The environment.
   */
  protected function generateMemcachedDeployment(NodeInterface $environment) {
    $memcachedDeploymentName = self::getMemcachedDeploymentName($environment);
    $deploymentName = OpenShiftOrchestrationProvider::generateDeploymentName($environment->id());
    $memcachedPort = 11211;

    if (!$image_stream = $this->client->getImageStream('memcached')) {
      $this->client->createImageStream($this->generateImageStream());
    }

    $data = $this->formatMemcachedDeployData($deploymentName, $environment->field_shp_site->target_id, $environment->id());
    $deployConfig = $this->generateDeploymentConfig($environment, $memcachedDeploymentName, $memcachedPort, $data);
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
      'apiVersion' => 'v1',
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
   *   Redis name.
   * @param string $memcached_port
   *   Redis port.
   * @param array $data
   *   Array of data for labels.
   *
   * @return array
   *   The deployment config array.
   */
  protected function generateDeploymentConfig(NodeInterface $environment, string $memcached_name, string $memcached_port, array $data) {
    $config = [
      'apiVersion' => 'v1',
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
                    'containerPort' => $memcached_port,
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
   * {@inheritdoc}
   */
  public function onEnvironmentDelete(NodeInterface $environment) {
    $deployment_name = OpenShiftOrchestrationProvider::generateDeploymentName($environment->id());
    $service_name = self::getMemcacheServiceName($deployment_name);
    if ($this->client->getService($service_name)) {
      $this->client->deleteService($service_name);
    }

    if (!$configMap = $this->client->getConfigmap($this->configMapName)) {
      return;
    }
    if (!$xml = $this->loadXml($configMap)) {
      return;
    }
    $name = self::getMemcacheName($environment);
    // Delete the socket-binding.
    if ($socket_binding = $this->findElement($xml, sprintf('//s:server/s:socket-binding-group/s:socket-binding[@name="%s"]', $name))) {
      $this->deleteXmlElement($socket_binding);
    }

    // Delete the memcache-connector.
    if ($memcached_connector = $this->findElement($xml, sprintf('//c:subsystem/c:memcached-connector[@name="%s"]', $name))) {
      $this->deleteXmlElement($memcached_connector);
    }

    // Delete the distributed-cache.
    if ($distributed_cache = $this->findElement($xml, sprintf('//dc:subsystem/dc:cache-container/dc:distributed-cache[@name="%s"]', $name))) {
      $this->deleteXmlElement($distributed_cache);
    }

    $configMap->setDataKey($this->jdgConfigFile, $this->formatXml($xml));
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

    // Delete the memcached deployment.
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
   * Finds an element by xpath and returns the first match.
   *
   * @param \SimpleXMLElement $xml
   *   The xml to search in.
   * @param string $xpath
   *   The xpath to search by.
   *
   * @return \SimpleXMLElement|false
   *   The element, or FALSE.
   */
  protected function findElement(\SimpleXMLElement $xml, $xpath) {
    if ($elements = $xml->xpath($xpath)) {
      return reset($elements);
    }
    return FALSE;
  }

  /**
   * Deletes an XML element.
   *
   * @param \SimpleXMLElement $el
   *   The element to delete.
   */
  protected function deleteXmlElement(\SimpleXMLElement $el) {
    $domRef = dom_import_simplexml($el);
    $domRef->parentNode->removeChild($domRef);
  }

  /**
   * Format the XML into a well formed string.
   *
   * @param \SimpleXMLElement $xml
   *   The xml.
   *
   * @return string
   *   Well formed XML.
   */
  protected function formatXml(\SimpleXMLElement $xml) {
    $dom = new \DOMDocument("1.0");
    $dom->preserveWhiteSpace = FALSE;
    $dom->formatOutput = TRUE;
    $dom->loadXML($xml->asXML());
    return $dom->saveXML();
  }

  /**
   * Loads the XML from the datagrid config map.
   *
   * @param \UniversityOfAdelaide\OpenShift\Objects\ConfigMap $configMap
   *   The config map.
   *
   * @return bool|\SimpleXMLElement
   *   An SimpleXMLElement, or FALSE on failure.
   */
  protected function loadXml(ConfigMap $configMap) {
    if (empty($configMap->getData()[$this->jdgConfigFile])) {
      return FALSE;
    }
    if (!$xml = simplexml_load_string($configMap->getData()[$this->jdgConfigFile])) {
      return FALSE;
    }
    // Register namespaces to query subsystems by.
    $xml->registerXPathNamespace('s', 'urn:jboss:domain:8.0');
    $xml->registerXPathNamespace('c', 'urn:infinispan:server:endpoint:9.4');
    $xml->registerXPathNamespace('dc', 'urn:infinispan:server:core:9.4');
    return $xml;
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
