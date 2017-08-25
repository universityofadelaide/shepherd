<?php

namespace Drupal\shp_orchestration;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\shp_orchestration\Exception\OrchestrationProviderNotConfiguredException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A base class to implement an orchestration provider plugin.
 *
 * @see \Drupal\shp_orchestration\Annotation\OrchestrationProvider
 * @see \Drupal\shp_orchestration\OrchestrationProviderInterface
 */
abstract class OrchestrationProviderBase extends PluginBase implements ContainerFactoryPluginInterface, OrchestrationProviderInterface {

  protected $entityTypeManager;

  protected $configEntity;

  protected $eventDispatcher;

  /**
   * OrchestrationProviderBase constructor.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Event dispatcher service
   *
   * @throws \Drupal\shp_orchestration\Exception\OrchestrationProviderNotConfiguredException
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $config_entity_id = $plugin_definition['config_entity_id'];
    $entity_manager = $this->entityTypeManager->getStorage($config_entity_id);
    $this->configEntity = $entity_manager->load($config_entity_id);
    if (!is_object($this->configEntity)) {
      throw new OrchestrationProviderNotConfiguredException(
        'Orchestration provider is not configured. Changes made in Shepherd will
        not be reflected in backend until this is completed.'
      );
    }

    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function description() {
    // Retrieve description property from the annotation.
    return $this->pluginDefinition['description'];
  }

  /**
   * Retrieves the related config entity.
   */
  public function getConfigEntity() {
    return $this->configEntity;
  }

}
