<?php

namespace Drupal\Tests\shp_cache_backend\Kernel;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeInterface;
use Drupal\shp_cache_backend\Plugin\CacheBackend\MemcachedDatagrid;
use Drupal\Tests\UnitTestCase;
use UniversityOfAdelaide\OpenShift\Client;
use UniversityOfAdelaide\OpenShift\Objects\ConfigMap;
use UniversityOfAdelaide\OpenShift\Objects\NetworkPolicy;
use UniversityOfAdelaide\OpenShift\Objects\StatefulSet;
use UniversityOfAdelaide\OpenShift\Serializer\OpenShiftSerializerFactory;

/**
 * @coversDefaultClass \Drupal\shp_cache_backend\Plugin\CacheBackend\MemcachedDatagrid
 */
class MemcachedDatagridCacheBackendKernelTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'shp_cache_backend',
    'shp_orchestration',
    'shp_custom',
    'serialization',
  ];

  /**
   * A mock OS client.
   *
   * @var \UniversityOfAdelaide\OpenShift\Client|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $client;

  /**
   * Our plugin to test.
   *
   * @var \Drupal\shp_cache_backend\Plugin\CacheBackend\MemcachedDatagrid
   */
  protected $plugin;

  /**
   * A mock environment node.
   *
   * @var \Drupal\node\NodeInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $environment;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Set up mocks for our plugin.
    $this->environment = $this->createMock(NodeInterface::class);
    $this->environment->expects($this->any())
      ->method('id')
      ->willReturn(123);
    $this->client = $this->createMock(Client::class);
    $config = $this->createMock(ImmutableConfig::class);
    $config->expects($this->any())
      ->method('get')
      ->willReturn('mynamespace');
    $this->plugin = new MemcachedDatagrid([], 'test', [], $this->client, $config);
  }

  /**
   * @covers ::getEnvironmentVariables
   */
  public function testGetEnvironmentVariables() {
    $this->assertEquals([
      'MEMCACHE_ENABLED' => '1',
      'MEMCACHE_HOST' => 'node-123-mc.mynamespace.svc.cluster.local',
    ], $this->plugin->getEnvironmentVariables($this->environment));
  }

  /**
   * @covers ::onEnvironmentCreate
   */
  public function testOnEnvironmentCreate() {
    $fixture_dir = __DIR__ . '/../../fixtures';
    // The client will create a network policy and service.
    $this->client->expects($this->once())
      ->method('createNetworkpolicy');
    $this->client->expects($this->once())
      ->method('createService');

    // The client will return a config map, with some XML in its data.
    $config_map = ConfigMap::create()
      ->setData([
        'something_else' => 'foo',
        'standalone.xml' => file_get_contents($fixture_dir . '/standalone.xml'),
      ]);
    $this->client->expects($this->once())
      ->method('getConfigmap')
      ->willReturn($config_map);
    // The client will receive an updated config map, with updated XML.
    $resulting_config_map = ConfigMap::create()
      ->setData([
        'something_else' => 'foo',
        'standalone.xml' => file_get_contents($fixture_dir . '/standalone_with123.xml'),
      ]);
    $this->client->expects($this->once())
      ->method('updateConfigmap')
      ->with($resulting_config_map);

    // The client will return a stateful set based on a JSON fixture.
    $os_serializer = OpenShiftSerializerFactory::create();
    /** @var \UniversityOfAdelaide\OpenShift\Objects\ConfigMap $configMap */
    $stateful_set = $os_serializer->deserialize(file_get_contents($fixture_dir . '/statefulset.json'), StatefulSet::class, 'json');
    $this->client->expects($this->once())
      ->method('getStatefulset')
      ->with('datagrid-app')
      ->willReturn($stateful_set);
    // The client will receive an updated statefulset with the new port added.
    $resulting_stateful_set = $os_serializer->deserialize(file_get_contents($fixture_dir . '/statefulset_with123.json'), StatefulSet::class, 'json');
    $this->client->expects($this->once())
      ->method('updateStatefulset')
      ->with($resulting_stateful_set);

    $this->plugin->onEnvironmentCreate($this->environment);
  }

  /**
   * @covers ::onEnvironmentDelete
   */
  public function testOnEnvironmentDelete() {
    $fixture_dir = __DIR__ . '/../../fixtures';
    // The client will get and delete a network policy and service.
    $this->client->expects($this->once())
      ->method('getNetworkpolicy')
      ->with('datagrid-allow-node-123')
      ->willReturn(NetworkPolicy::create());
    $this->client->expects($this->once())
      ->method('deleteNetworkpolicy')
      ->with('datagrid-allow-node-123');
    $this->client->expects($this->once())
      ->method('getService')
      ->with('node-123-mc')
      ->willReturn(TRUE);
    $this->client->expects($this->once())
      ->method('deleteService')
      ->with('node-123-mc');

    // The client will return a config map, with XML containing the
    // environment's memcache definitions.
    $config_map = ConfigMap::create()
      ->setData([
        'something_else' => 'foo',
        'standalone.xml' => file_get_contents($fixture_dir . '/standalone_with123.xml'),
      ]);
    $this->client->expects($this->once())
      ->method('getConfigmap')
      ->willReturn($config_map);
    // The client will receive an updated config map with the environment's
    // memcache definitions removed.
    $resulting_config_map = ConfigMap::create()
      ->setData([
        'something_else' => 'foo',
        'standalone.xml' => file_get_contents($fixture_dir . '/standalone.xml'),
      ]);
    $this->client->expects($this->once())
      ->method('updateConfigmap')
      ->with($resulting_config_map);

    // The client will return a stateful set containing the environment's port.
    $os_serializer = OpenShiftSerializerFactory::create();
    /** @var \UniversityOfAdelaide\OpenShift\Objects\ConfigMap $configMap */
    $stateful_set = $os_serializer->deserialize(file_get_contents($fixture_dir . '/statefulset_with123.json'), StatefulSet::class, 'json');
    $this->client->expects($this->once())
      ->method('getStatefulset')
      ->with('datagrid-app')
      ->willReturn($stateful_set);
    // The client will receive a stateful set with the environment's port
    // removed.
    $resulting_stateful_set = $os_serializer->deserialize(file_get_contents($fixture_dir . '/statefulset.json'), StatefulSet::class, 'json');
    $this->client->expects($this->once())
      ->method('updateStatefulset')
      ->with($resulting_stateful_set);

    $this->plugin->onEnvironmentDelete($this->environment);
  }

}
