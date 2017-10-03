<?php

namespace Drupal\shp_orchestration;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\shp_orchestration\Annotation\OrchestrationProvider;

/**
 * Class OrchestrationProviderPluginManager.
 *
 * @package Drupal\shp_orchestration
 */
class OrchestrationProviderPluginManager extends DefaultPluginManager implements OrchestrationProviderPluginManagerInterface {

  /**
   * Instance of orchestration provider.
   *
   * @var object
   */
  protected $providerInstance;

  /**
   * Creates the discovery object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {

    $subdir = 'Plugin/OrchestrationProvider';
    $plugin_interface = OrchestrationProviderInterface::class;
    $plugin_definition_annotation_name = OrchestrationProvider::class;
    parent::__construct($subdir, $namespaces, $module_handler, $plugin_interface, $plugin_definition_annotation_name);
    $this->alterInfo('shp_orchestration_orchestration_provider_info');
    $this->setCacheBackend($cache_backend, 'shp_orchestration_orchestration_provider');
  }

  /**
   * {@inheritdoc}
   */
  public function getSelectedProvider() {
    $config_factory = \Drupal::service('config.factory');
    $selected_provider = $config_factory->get('shp_orchestration.settings')->get('selected_provider');
    $definitions = $this->getDefinitions();
    return $definitions[$selected_provider];
  }

  /**
   * {@inheritdoc}
   */
  public function getProviderInstance() {
    if (isset($this->providerInstance)) {
      return $this->providerInstance;
    }
    $id = $this->getSelectedProvider()['id'];
    $this->providerInstance = $this->createInstance($id);
    return $this->providerInstance;
  }

}
