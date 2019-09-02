<?php

namespace Drupal\shp_cache_backend\Plugin\CacheBackend;

use Drupal\node\NodeInterface;
use Drupal\shp_cache_backend\Plugin\CacheBackendBase;
use Drupal\shp_orchestration\Plugin\OrchestrationProvider\OpenShiftOrchestrationProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\Serializer;
use UniversityOfAdelaide\OpenShift\Client;
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
   * The JGD config file.
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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Client $client, Serializer $serializer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $client);
    $this->serializer = $serializer;
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
      $container->get('serializer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironmentVariables(NodeInterface $environment) {
    $deployment_name = OpenShiftOrchestrationProvider::generateDeploymentName($environment->id());
    return [
      'MEMCACHE_ENABLED' => '1',
      'MEMCACHE_HOST' => $deployment_name . '-mc' . '.svc.cluster.local',
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

    if (empty($configMap->getData()[$this->jdgConfigFile])) {
      return;
    }
    $name = 'memcached_node' . $environment->id();
    $data = $configMap->getData();
    $xml = simplexml_load_string($data[$this->jdgConfigFile]);
    $xml->registerXPathNamespace('connectors', 'urn:infinispan:server:endpoint:9.4');
    $xml->registerXPathNamespace('dc', 'urn:infinispan:server:core:9.4');

    // Find the next port to use.
    $max_port = $this->defaultPort;
    foreach ($xml->{'socket-binding-group'}->{'socket-binding'} as $binding) {
      $binding_name = (string) $binding->attributes()->name;
      $port = (int) $binding->attributes()->port;
      if (strpos($binding_name, 'memcached_node') === 0 && !empty($port)) {
        if ($port > $max_port) {
          $max_port = $port;
        }
      }
    }
    $new_port = $max_port + 1;

    // Create the NetworkPolicy.
    $datagrid_selector = 'datagrid-app';
    $network_policy = NetworkPolicy::create()
      ->setIngressMatchLabels(['app' => $deployment_name])
      // @todo make this configurable.
      ->setPodSelectorMatchLabels(['application' => $datagrid_selector])
      ->setPort($new_port)
      ->setName('datagrid-allow-' . $deployment_name);
    // @todo error handling.
    $this->client->createNetworkpolicy($network_policy);

    // Create the Service.
    $this->client->createService(
      $deployment_name . '-mc',
      $datagrid_selector,
      11211,
      $new_port,
      $deployment_name
    );

    // Add the socket-binding.
    $socket_binding = $xml->{'socket-binding-group'}->addChild('socket-binding');
    $socket_binding->addAttribute('name', $name);
    $socket_binding->addAttribute('port', $new_port);

    // Add the memcache-connector element.
    $connector_subsystem = $xml->xpath('//connectors:subsystem')[0];
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

    $data[$this->jdgConfigFile] = $xml->asXML();
    $configMap->setData($data);
    $this->client->updateConfigmap($configMap);
  }

  /**
   * {@inheritdoc}
   */
  public function onEnvironmentDelete(NodeInterface $environment) {
    // TODO: Implement onEnvironmentDelete() method.
  }

}
