<?php

namespace Drupal\Tests\shp_cache_backend\Unit;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\shp_cache_backend\Plugin\CacheBackend\MemcachedDatagrid;
use Drupal\shp_custom\Service\EnvironmentType;
use Drupal\shp_service_accounts\Entity\ServiceAccount;
use Drupal\shp_service_accounts\Service\ServiceAccountsInterface;
use Drupal\Tests\UnitTestCase;
use UniversityOfAdelaide\OpenShift\Client;
use UniversityOfAdelaide\OpenShift\Objects\ConfigMap;
use UniversityOfAdelaide\OpenShift\Objects\StatefulSet;
use UniversityOfAdelaide\OpenShift\Serializer\OpenShiftSerializerFactory;

/**
 * @coversDefaultClass \Drupal\shp_cache_backend\Plugin\CacheBackend\MemcachedDatagrid
 * @group shepherd
 */
class MemcachedDatagridCacheBackendTest extends UnitTestCase {

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
   * @var \UniversityOfAdelaide\OpenShift\Client|\PHPUnit\Framework\MockObject\MockObject
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
   * @var \Drupal\node\NodeInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $environment;

  /**
   * A mock environment type service.
   *
   * @var \Drupal\shp_custom\Service\EnvironmentType|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $environmentType;

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
    $this->environment->field_shp_site = new \stdClass();

    $this->site = $this->createMock(NodeInterface::class);
    $this->site->expects($this->any())
      ->method('id')
      ->willReturn(456);

    $this->environment->field_shp_site->entity = $this->site;
    $this->environment->field_shp_site->target_id = 456;
    $this->client = $this->createMock(Client::class);
    $config = $this->createMock(ImmutableConfig::class);
    $config->expects($this->any())
      ->method('get')
      ->willReturnMap([
        ['connection.token', 'mytoken'],
        ['connection.namespace', 'mynamespace'],
        ['site_deploy_prefix', 'test-'],
      ]);

    $secret = [
      'data' => [
        'token' => 'Ym9id2FzaGVyZQo=',
      ],
    ];
    $this->client->method('getSecret')
      ->willReturn($secret);

    $this->environmentType = $this->createMock(EnvironmentType::class);
    $this->plugin = new MemcachedDatagrid([], 'test', [], $this->client, $config, $this->environmentType);

    // Provide a mock service container, for the services our module uses.
    $container = new ContainerBuilder();
    $container->set('shp_service_accounts', $this->getServiceAccountsMock());
    $container->set('entity_type.manager', $this->getEntityTypeManagerMock());
    \Drupal::setContainer($container);
  }

  /**
   * Creates and returns a mock for service accounts service.
   */
  protected function getServiceAccountsMock() {
    $serviceAccountsMock = $this->getMockBuilder(ServiceAccountsInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $serviceAccountsMock->method('getServiceAccount')
      ->will($this->returnValue(
        new ServiceAccount([
          'id' => 1,
          'title' => 'Doesnt matter 1',
          'status' => TRUE,
          'token' => 'ooohspecialtoken',
        ], 'service_account')));

    return $serviceAccountsMock;
  }

  /**
   * Mock of the entity type manager to return a node.
   */
  protected function getEntityTypeManagerMock() {
    $fieldShortNameMock = $this->getMockBuilder(FieldItemListInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $fieldShortNameMock->expects($this->any())
      ->method('__get')
      ->with('value')
      ->willReturn('wedontactuallycare');

    $nodeMock = $this->getMockBuilder(Node::class)
      ->disableOriginalConstructor()
      ->getMock();
    $nodeMock->expects($this->any())
      ->method('__get')
      ->with('field_shp_short_name')
      ->willReturn($fieldShortNameMock);

    $nodeStorage = $this->createMock(NodeStorageInterface::class);
    $nodeStorage->expects($this->any())
      ->method('load')
      ->willReturn($nodeMock);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->with('node')
      ->willReturn($nodeStorage);

    return $entityTypeManager;
  }

  /**
   * @covers ::getEnvironmentVariables
   *
   * @dataProvider getEnvironmentVarsProvider
   */
  public function testGetEnvironmentVariables($isPromoted, $host) {
    $this->environmentType->expects($this->once())
      ->method('isPromotedEnvironment')
      ->willReturn($isPromoted);
    $this->assertEquals([
      'MEMCACHE_ENABLED' => '1',
      'MEMCACHE_HOST' => $host,
    ], $this->plugin->getEnvironmentVariables($this->environment));
  }

  /**
   * Data provider for testGetEnvironmentVariables.
   */
  public function getEnvironmentVarsProvider() {
    return [
      [TRUE, 'node-123-mc.mynamespace.svc.cluster.local'],
      [FALSE, 'node-123-memcached'],
    ];
  }

  /**
   * @covers ::onEnvironmentCreate
   */
  public function testOnEnvironmentCreate() {
    $fixture_dir = __DIR__ . '/../../fixtures';
    // The client will create a  service.
    $this->client->expects($this->exactly(2))
      ->method('createService');
    $this->client->expects($this->once())
      ->method('getImageStream');
    $this->client->expects($this->once())
      ->method('createImageStream');
    $this->client->expects($this->once())
      ->method('createDeploymentConfig');

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
    // The client will get and delete a service.
    $this->client->expects($this->exactly(2))
      ->method('getService')
      ->with($this->logicalOr('node-123-mc', 'node-123-memcached'))
      ->willReturn(TRUE);
    $this->client->expects($this->exactly(2))
      ->method('deleteService')
      ->with($this->logicalOr('node-123-mc', 'node-123-memcached'));
    $this->client->expects($this->once())
      ->method('getDeploymentConfig')
      ->with('node-123-memcached')
      ->willReturn(TRUE);
    $this->client->expects($this->once())
      ->method('deleteDeploymentConfig')
      ->with('node-123-memcached');
    $this->client->expects($this->once())
      ->method('deleteReplicationControllers')
      ->with('', 'openshift.io/deployment-config.name=node-123-memcached');

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
