<?php

namespace Drupal\shp_backup;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class BackupServiceProvider.
 */
class BackupServiceProvider extends ServiceProviderBase {

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * EnvironmentRestoreForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The backup service.
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($this->configFactory->get('shp_orchestration.queued_operations')) {
      // Overrides backup service to use a queued backup service.
      $definition = $container->getDefinition('shp_backup.backup');
      $definition->setClass('Drupal\shp_backup\Service\BackupServiceProvider');
      $arguments   = $definition->getArguments();
      $arguments[] = new Reference('shp_orchestration.job_queue');
      $definition->setArguments($arguments);
    }
  }

}
