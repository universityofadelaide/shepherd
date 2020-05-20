<?php

namespace Drupal\shp_orchestration\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\shp_orchestration\OrchestrationProviderPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class OrchestrationProviderSettingsController.
 *
 * @package Drupal\shp_orchestration\Controller
 */
class OrchestrationProviderSettingsController extends ControllerBase {

  /**
   * Orchestration provider manager.
   *
   * @var \Drupal\shp_orchestration\OrchestrationProviderPluginManagerInterface
   */
  protected $orchestrationProviderManager;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Provider plugin.
   *
   * @var array
   */
  protected $selectedProviderPlugin;

  /**
   * Entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $entityFormBuilder;

  /**
   * Constructs a OrchestrationProviderSettingsController.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\shp_orchestration\OrchestrationProviderPluginManagerInterface $orchestration_provider_manager
   *   Orchestration Provider Manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   *   Entity Form Builder.
   */
  public function __construct(ConfigFactoryInterface $config_factory,
                              OrchestrationProviderPluginManagerInterface $orchestration_provider_manager,
                              EntityTypeManagerInterface $entity_type_manager,
                              EntityFormBuilderInterface $entity_form_builder) {

    $this->configFactory = $config_factory;
    $this->orchestrationProviderManager = $orchestration_provider_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;

    $this->selectedProviderPlugin = $this->orchestrationProviderManager->getSelectedProvider();

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.orchestration_provider'),
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder')
    );
  }

  /**
   * Process request and load current orchestration provider settings form.
   */
  public function index() {
    $form = [];

    if (isset($this->selectedProviderPlugin) && !empty($this->selectedProviderPlugin)) {
      $config_entity_id = $this->selectedProviderPlugin['config_entity_id'];
      $entity_manager = $this->entityTypeManager->getStorage($config_entity_id);
      $entity = $entity_manager->load($config_entity_id);
      if (!$entity) {
        $entity = $entity_manager->create([
          'type' => $config_entity_id,
        ]);
      }
      $form = $this->entityFormBuilder->getForm($entity, 'add');
    }

    return $form;
  }

  /**
   * Callback to get the page title.
   *
   * @return string
   *   The label name of the selected plugin.
   */
  public function getPageTitle() {
    return $this->t('@name provider settings', ['@name' => $this->selectedProviderPlugin['name']]);
  }

}
