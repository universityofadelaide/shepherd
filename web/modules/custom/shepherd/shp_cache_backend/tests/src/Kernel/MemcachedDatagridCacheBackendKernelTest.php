<?php

namespace Drupal\Tests\shp_cache_backend\Kernel;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeInterface;
use Drupal\shp_cache_backend\Plugin\CacheBackend\MemcachedDatagrid;
use UniversityOfAdelaide\OpenShift\Client;
use UniversityOfAdelaide\OpenShift\Objects\ConfigMap;
use UniversityOfAdelaide\OpenShift\Objects\NetworkPolicy;

/**
 * @coversDefaultClass \Drupal\shp_cache_backend\Plugin\CacheBackend\MemcachedDatagrid
 */
class MemcachedDatagridCacheBackendKernelTest extends KernelTestBase {

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

    $this->environment = $this->createMock(NodeInterface::class);
    $this->environment->expects($this->any())
      ->method('id')
      ->willReturn(123);
    $this->client = $this->createMock(Client::class);
    $serializer = \Drupal::service('serializer');
    $config = $this->createMock(ImmutableConfig::class);
    $config->expects($this->any())
      ->method('get')
      ->willReturn('mynamespace');
    $this->plugin = new MemcachedDatagrid([], 'test', [], $this->client, $serializer, $config);
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
    $this->client->expects($this->once())
      ->method('createNetworkpolicy');
    $this->client->expects($this->once())
      ->method('createService');
    $config_map = ConfigMap::create()
      ->setData([
        'something_else' => 'foo',
        'standalone.xml' => file_get_contents(drupal_get_path('module', 'shp_cache_backend') . '/tests/fixtures/standalone.xml'),
      ]);
    $this->client->expects($this->once())
      ->method('getConfigmap')
      ->willReturn($config_map);
    $resulting_config_map = ConfigMap::create()
      ->setData([
        'something_else' => 'foo',
        'standalone.xml' => file_get_contents(drupal_get_path('module', 'shp_cache_backend') . '/tests/fixtures/standalone_with123.xml'),
      ]);
    $this->client->expects($this->once())
      ->method('updateConfigmap')
      ->with($resulting_config_map);
    $this->plugin->onEnvironmentCreate($this->environment);
  }

  /**
   * @covers ::onEnvironmentDelete
   */
  public function testOnEnvironmentDelete() {
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
    $config_map = ConfigMap::create()
      ->setData([
        'something_else' => 'foo',
        'standalone.xml' => file_get_contents(drupal_get_path('module', 'shp_cache_backend') . '/tests/fixtures/standalone_with123.xml'),
      ]);
    $this->client->expects($this->once())
      ->method('getConfigmap')
      ->willReturn($config_map);
    $resulting_config_map = ConfigMap::create()
      ->setData([
        'something_else' => 'foo',
        'standalone.xml' => file_get_contents(drupal_get_path('module', 'shp_cache_backend') . '/tests/fixtures/standalone.xml'),
      ]);
    $this->client->expects($this->once())
      ->method('updateConfigmap')
      ->with($resulting_config_map);
    $this->plugin->onEnvironmentDelete($this->environment);
  }

}
