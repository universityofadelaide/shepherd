<?php

namespace Drupal\shp_time_restrictions\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
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

  use StringTranslationTrait;

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
    if ($this->nodeActionsLogService->isOutsideEnvironmentCreationTimeDelay()) {
      // No need to generate block content.
      return;
    }

<<<<<<< HEAD
    $next_expiration = $this->nodeActionsLogService->getNextExpiration();

    $text = $this->t('To prevent errors there is a delay between environment actions. The next action is allowed in @seconds seconds',
      ['@seconds' => $next_expiration - time()]);
=======
    $time_delay = $this->configFactory->get('shp_time_restrictions.settings')->get('environment_creation_time_delay');
    $last_action_time = $this->nodeActionsLogService->getLastActionTimestamp();

    $text = $this->t('To prevent errors there is a delay between environment actions. The next action is allowed in @seconds seconds',
      ['@seconds' => $time_delay - (time() - $last_action_time)]);
>>>>>>> 8eec280068c7240555b3bc6a983dd220643459e1

    $build['content'] = [
      '#markup' =>
      '<div class="messages messages--warning">' . $text . '</div>',
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
