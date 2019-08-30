<?php

namespace Drupal\shp_cache_backend\Plugin;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UniversityOfAdelaide\OpenShift\Client;

/**
 * Defines an interface for CacheBackend plugins.
 */
abstract class CacheBackendBase extends PluginBase implements ContainerFactoryPluginInterface, CacheBackendInterface {

  /**
   * The OS Client.
   *
   * @var \UniversityOfAdelaide\OpenShift\Client
   */
  protected $client;

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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Client $client) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('shp_orchestration.client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironmentVariables(NodeInterface $environment) {
    return [];
  }

}
