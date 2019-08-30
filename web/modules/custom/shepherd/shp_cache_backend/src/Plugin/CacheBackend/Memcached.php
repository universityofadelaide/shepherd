<?php

namespace Drupal\shp_cache_backend\Plugin\CacheBackend;

use Drupal\node\NodeInterface;
use Drupal\shp_cache_backend\Plugin\CacheBackendBase;

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
    $normalized = $serializer->decode($configMap->getData()['standalone.xml'], 'xml');
    // Find the next port to be used.
    $ports = array_column(array_filter($normalized['socket-binding-group']['socket-binding'], function ($binding) {
      return strpos($binding['@name'], 'memcached_node') === 0 && isset($binding['@port']);
    }), '@port');
    $new_port = max($ports) + 1;

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
    $xml = $serializer->encode($normalized, 'xml', ['xml_root_node_name' => 'server']);
  }

  /**
   * {@inheritdoc}
   */
  public function onEnvironmentDelete(NodeInterface $environment) {
    // TODO: Implement onEnvironmentDelete() method.
  }

}
