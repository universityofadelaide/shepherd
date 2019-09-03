<?php

namespace Drupal\shp_cache_backend\Plugin\CacheBackend;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\node\NodeInterface;
use Drupal\shp_cache_backend\Plugin\CacheBackendBase;
use Drupal\shp_orchestration\Plugin\OrchestrationProvider\OpenShiftOrchestrationProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\Serializer;
use UniversityOfAdelaide\OpenShift\Client;
use UniversityOfAdelaide\OpenShift\Objects\ConfigMap;
use UniversityOfAdelaide\OpenShift\Objects\NetworkPolicy;

/**
 * Provides Redis integration.
 *
 * @CacheBackend(
 *   id = "memcached",
 *   label = @Translation("Memcached")
 * )
 */
class Memcached extends CacheBackendBase {

  /**
   * The serializer service.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The namespace objects are being created in.
   *
   * @var string
   */
  protected $namespace;

  /**
   * The JGD config file.
   *
   * TODO: make this name configurable?
   *
   * @var string
   */
  protected $jdgConfigFile = 'standalone.xml';

  /**
   * The config map name.
   *
   * TODO: make this name configurable?
   *
   * @var string
   */
  protected $configMapName = 'datagrid-config';

  /**
   * The default port to create a memcache instance for.
   *
   * @var int
   */
  protected $defaultPort = 11311;

  /**
   * The pod selector used in network policies.
   *
   * TODO: make this name configurable?
   *
   * @var string
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
   * @param \Symfony\Component\Serializer\Serializer $serializer
   *   The serializer service.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The orchestration config.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Client $client, Serializer $serializer, ImmutableConfig $config) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $client);
    $this->serializer = $serializer;
    $this->namespace = $config->get('connection.namespace');
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
      $container->get('serializer'),
      $container->get('config.factory')->get('shp_orchestration.settings')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironmentVariables(NodeInterface $environment) {
    $deployment_name = OpenShiftOrchestrationProvider::generateDeploymentName($environment->id());
    return [
      'MEMCACHE_ENABLED' => '1',
      'MEMCACHE_HOST' => sprintf(
        '%s.%s.svc.cluster.local',
        self::getMemcacheServiceName($deployment_name),
        $this->namespace
      ),
    ];
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

    // Create the NetworkPolicy.
    $network_policy = NetworkPolicy::create()
      ->setIngressMatchLabels(['app' => $deployment_name])
      ->setPodSelectorMatchLabels(['application' => $this->datagridSelector])
      ->setPort($new_port)
      ->setName(self::getNetworkPolicyName($deployment_name));
    $this->client->createNetworkpolicy($network_policy);

    // Create the Service.
    $this->client->createService(
      self::getMemcacheServiceName($deployment_name),
      $this->datagridSelector,
      11211,
      $new_port,
      $deployment_name
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
  }

  /**
   * {@inheritdoc}
   */
  public function onEnvironmentDelete(NodeInterface $environment) {
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
    return 'memcached_node' . $environment->id();
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

}
