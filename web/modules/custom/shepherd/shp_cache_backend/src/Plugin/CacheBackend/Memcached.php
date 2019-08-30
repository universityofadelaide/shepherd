<?php

namespace Drupal\shp_cache_backend\Plugin\CacheBackend;

use Drupal\node\NodeInterface;
use Drupal\shp_cache_backend\Plugin\CacheBackendBase;
use Drupal\shp_orchestration\Plugin\OrchestrationProvider\OpenShiftOrchestrationProvider;
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
   * {@inheritdoc}
   */
  public function onEnvironmentCreate(NodeInterface $environment) {
    $deployment_name = OpenShiftOrchestrationProvider::generateDeploymentName($environment->id());
    // @todo: make this name configurable?
    if (!$configMap = $this->client->getConfigmap('datagrid-config')) {
      return;
    }

    if (empty($configMap->getData()['standalone.xml'])) {
      return;
    }
    $name = 'memcached_node' . $environment->id();

    /** @var \Symfony\Component\Serializer\Serializer $serializer */
    $serializer = \Drupal::service('serializer');
    $data = $configMap->getData();
    $normalized = $serializer->decode($data['standalone.xml'], 'xml');
    // Find the next port to be used.
    $ports = array_column(array_filter($normalized['socket-binding-group']['socket-binding'], function ($binding) {
      return strpos($binding['@name'], 'memcached_node') === 0 && isset($binding['@port']);
    }), '@port');
    $new_port = max($ports) + 1;

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
    $normalized['socket-binding-group']['socket-binding'][] = [
      '@name' => $name,
      '@port' => $new_port,
    ];

    // Add the memcache-connector element.
    foreach ($normalized['profile']['subsystem'] as $idx => $subsystem) {
      if (!array_key_exists('memcached-connector', $subsystem)) {
        continue;
      }
      $subsystem['memcached-connector'][] = [
        '@name' => $name,
        '@cache-container' => 'clustered',
        '@cache' => $name,
        '@socket-binding' => $name,
      ];
      $normalized['profile']['subsystem'][$idx] = $subsystem;
      break;
    }
    // Add the distributed-cache element.
    foreach ($normalized['profile']['subsystem'] as $idx => $subsystem) {
      if (!array_key_exists('cache-container', $subsystem)) {
        continue;
      }
      $subsystem['cache-container']['distributed-cache'][] = [
        '@statistics' => "true",
        '@name' => $name,
        '@mode' => 'SYNC',
        '@start' => 'EAGER',
        'memory' => [
          'object' => '',
        ],
      ];
      $normalized['profile']['subsystem'][$idx] = $subsystem;
      break;
    }
    $data['standalone.xml'] = $serializer->encode($normalized, 'xml', ['xml_root_node_name' => 'server']);
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
