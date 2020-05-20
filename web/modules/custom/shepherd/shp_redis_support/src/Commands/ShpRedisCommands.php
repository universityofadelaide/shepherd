<?php

namespace Drupal\shp_redis_support\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\shp_orchestration\OrchestrationProviderPluginManager;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Drush\Utils\StringUtils;

/**
 * Shepherd Redis Drush command file.
 */
class ShpRedisCommands extends DrushCommands {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Orchestration provider manager.
   *
   * @var \Drupal\shp_orchestration\OrchestrationProviderPluginManager
   */
  protected $orchestrationProviderPluginManager;

  /**
   * Orchestration provider.
   *
   * @var \Drupal\shp_orchestration\OrchestrationProviderInterface
   */
  protected $orchestrationProviderPlugin;

  /**
   * ShpRedisCommands constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\shp_orchestration\OrchestrationProviderPluginManager $orchestrationProviderPluginManager
   *   Orchestration provider plugin manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, OrchestrationProviderPluginManager $orchestrationProviderPluginManager) {
    parent::__construct();
    $this->orchestrationProviderPluginManager = $orchestrationProviderPluginManager;
    $this->orchestrationProviderPlugin = $orchestrationProviderPluginManager->getProviderInstance();
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Replace redis deployments.
   *
   * @param array $environments
   *   A comma delimited list of environments.
   *
   * @usage drush shepherd:recreate-redis-deployments
   *   Replace all redis deployments.
   *
   * @usage drush shepherd:recreate-redis-deployments 3245 1234
   *   Replace specific redis deployments.
   *
   * @command shepherd:recreate-redis-deployments
   */
  public function recreateRedisDeployments(array $environments) {
    if (!$this->io()->confirm(dt('Are you sure you want to redeploy all redis containers?'), FALSE)) {
      throw new UserAbortException();
    }

    // Find all the environments.
    if (empty($environments)) {
      $storage = $this->entityTypeManager->getStorage('node');
      $env_ids = $storage->getQuery()
        ->condition('type', 'shp_environment')
        ->execute();

      // Fix environment names to prepend "node".
      $environments = array_map(function ($env) {
        return 'node-' . $env;
      }, $env_ids);
    }
    else {
      $environments = StringUtils::csvToArray($environments);
    }

    // Replace redis deployment configs.
    foreach ($environments as $environment) {
      $this->orchestrationProviderPlugin->deleteRedisDeployment($environment);
      sleep(1);
      $this->orchestrationProviderPlugin->createRedisDeployment($environment);
      $this->output->writeln($environment . ' redis deployment updated.');
    }
    $this->output->writeln(count($environments) . ' redis deployments updated.');
  }

}
