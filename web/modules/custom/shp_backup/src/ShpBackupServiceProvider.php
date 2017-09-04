<?php

namespace Drupal\shp_backup;

use Drupal\Core\Config\BootstrapConfigStorageFactory;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class ShpBackupServiceProvider.
 */
class ShpBackupServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($this->isQueueEnabled()) {
      // Overrides backup service to use a queued backup service.
      $definition = $container->getDefinition('shp_backup.backup');
      $definition->setClass('Drupal\shp_backup\Service\QueuedBackup');
      $arguments = $definition->getArguments();
      $arguments[] = new Reference('shp_orchestration.job_queue');
      $definition->setArguments($arguments);
    }
  }

  /**
   * Retrieve queue setting from config.
   *
   * @return bool
   *   Is queuing enabled?
   */
  protected function isQueueEnabled() {
    $config_storage = BootstrapConfigStorageFactory::get();
    $config = $config_storage->read('shp_orchestration.settings');
    return $config['queued_operations'];
  }

}
