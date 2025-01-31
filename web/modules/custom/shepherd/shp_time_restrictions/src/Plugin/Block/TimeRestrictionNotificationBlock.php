<?php

namespace Drupal\shp_time_restrictions\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\shp_time_restrictions\ActionsLogService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a time restriction notification block.
 *
 * @Block(
 *   id = "shp_time_restrictions_time_restriction_notification",
 *   admin_label = @Translation("Time Restriction Notification"),
 *   category = @Translation("Shepherd")
 * )
 */
class TimeRestrictionNotificationBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The NodeActionLogger service.
   *
   * @var \Drupal\shp_time_restrictions\ActionsLogService
   */
  protected ActionsLogService $nodeActionsLogService;

  /**
   * Constructs a new TimeRestrictionNotificationBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\shp_time_restrictions\ActionsLogService $nodeActionsLogService
   *   The Actions Log service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, ActionsLogService $nodeActionsLogService) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->nodeActionsLogService = $nodeActionsLogService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('shp_time_restrictions.actions_log')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['content'] = [
      '#markup' => !($this->nodeActionsLogService->isOutsideEnvironmentCreationTimeDelay()) ?
      '<div class="messages messages--warning">Environment actions are not permitted at this time</div>' : FALSE,
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // Disables cache.
    return 0;
  }

}
