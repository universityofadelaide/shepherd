<?php

namespace Drupal\shp_orchestration;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
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
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   *   Config Factory.
   */
  protected $configFactory;

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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config_factory) {

    $subdir = 'Plugin/OrchestrationProvider';
    $plugin_interface = OrchestrationProviderInterface::class;
    $plugin_definition_annotation_name = OrchestrationProvider::class;
    parent::__construct($subdir, $namespaces, $module_handler, $plugin_interface, $plugin_definition_annotation_name);
    $this->alterInfo('shp_orchestration_orchestration_provider_info');
    $this->setCacheBackend($cache_backend, 'shp_orchestration_orchestration_provider');
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getSelectedProvider() {
    $selected_provider = $this->configFactory->get('shp_orchestration.settings')->get('selected_provider');
    $definitions = $this->getDefinitions();
    return $definitions[$selected_provider];
  }

  /**
   * {@inheritdoc}
   */
  public function getProviderInstance($reload = FALSE) {
    if (!$reload && isset($this->providerInstance)) {
      return $this->providerInstance;
    }
    if (!$id = $this->getSelectedProvider()) {
      return NULL;
    }
    $this->providerInstance = $this->createInstance($id['id']);
    return $this->providerInstance;
  }

}
