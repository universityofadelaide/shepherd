<?php

namespace Drupal\shp_backup\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\shp_custom\Service\Environment;
use Drupal\shp_custom\Service\EnvironmentType;
use Drupal\shp_orchestration\OrchestrationProviderPluginManagerInterface;

/**
 * Provides a service for accessing the backups.
 *
 * @package Drupal\shp_backup
 */
class Backup {

  use StringTranslationTrait;

  /**
   * The orchestration provider plugin manager.
   *
   * @var \Drupal\shp_orchestration\OrchestrationProviderInterface
   */
  protected $orchestrationProvider;

  /**
   * The environment type service.
   *
   * @var \Drupal\shp_custom\Service\EnvironmentType
   */
  protected $environmentType;

  /**
   * The environment service.
   *
   * @var \Drupal\shp_custom\Service\Environment
   */
  protected $environmentService;

  /**
   * Backup constructor.
   *
   * @param \Drupal\shp_orchestration\OrchestrationProviderPluginManagerInterface $pluginManager
   *   The orchestration provider plugin manager.
   * @param \Drupal\shp_custom\Service\EnvironmentType $environmentType
   *   The environment type service.
   * @param \Drupal\shp_custom\Service\Environment $environmentService
   *   The environment service.
   */
  public function __construct(OrchestrationProviderPluginManagerInterface $pluginManager, EnvironmentType $environmentType, Environment $environmentService) {
    $this->orchestrationProvider = $pluginManager->getProviderInstance();
    $this->environmentType = $environmentType;
    $this->environmentService = $environmentService;
  }

  /**
   * Gets a list of backups for all the environments of a site.
   *
   * @param \Drupal\node\NodeInterface $site
   *   Site node to retrieve the list of backups for.
   *
   * @return \UniversityOfAdelaide\OpenShift\Objects\Backups\BackupList|bool
   *   The backup list if successful otherwise false.
   */
  public function getAllForSite(NodeInterface $site) {
    return $this->orchestrationProvider->getBackupsForSite($site->id());
  }

  /**
   * Gets a list of backups for an environment.
   *
   * @param \Drupal\node\NodeInterface $environment
   *   Environment node to retrieve the list of backups for.
   *
   * @return \UniversityOfAdelaide\OpenShift\Objects\Backups\BackupList|bool
   *   The backup list if successful otherwise false.
   */
  public function getAllForEnvironment(NodeInterface $environment) {
    $site = $environment->field_shp_site->entity;
    return $this->orchestrationProvider->getBackupsForEnvironment($site, $environment->id());
  }

  /**
   * Create a backup node for a given site and environment.
   *
   * @param \Drupal\node\NodeInterface $site
   *   The site the environment blongs to.
   * @param \Drupal\node\NodeInterface $environment
   *   The environment to create the backup node for.
   * @param string $friendly_name
   *   An optional friendly name to set on the backup.
   *
   * @return \UniversityOfAdelaide\OpenShift\Objects\Backups\Backup|bool
   *   The backup object, or false.
   */
  public function createBackup(NodeInterface $site, NodeInterface $environment, string $friendly_name = '') {
    return $this->orchestrationProvider->backupEnvironment(
      $site->id(),
      $environment->id(),
      $friendly_name
    );
  }

  /**
   * Restore a backup for a given instance.
   *
   * @param string $backup_name
   *   The name of the backup to restore.
   * @param \Drupal\node\NodeInterface $environment
   *   The environment to restore.
   *
   * @return bool
   *   True on success.
   */
  public function restore(string $backup_name, NodeInterface $environment) {
    $site = $environment->field_shp_site->entity;
    return $this->orchestrationProvider->restoreEnvironment(
      $backup_name,
      $site->id(),
      $environment->id()
    );
  }

  /**
   * Upgrades an environment by provisioning a new one and issuing a sync.
   *
   * @param \Drupal\node\NodeInterface $site
   *   The site.
   * @param \Drupal\node\NodeInterface $environment
   *   The environment.
   * @param string $version
   *   The version to upgrade to.
   *
   * @return \Drupal\node\NodeInterface|bool
   *   The new environment if successful, else FALSE.
   */
  public function upgrade(NodeInterface $site, NodeInterface $environment, $version) {
    $demoted_term = $this->environmentType->getDemotedTerm();
    $ignore_env_vars = [
      'REDIS_ENABLED',
      'REDIS_HOST',
      'REDIS_PREFIX',
      'MEMCACHE_ENABLED',
      'MEMCACHE_HOST',
    ];
    $new_environment = Node::create([
      'type' => 'shp_environment',
      'moderation_state' => 'published',
      'field_shp_site' => $site->id(),
      'field_shp_git_reference' => $version,
      'field_shp_environment_type' => $demoted_term->id(),
      'field_cache_backend' => [
        'plugin_id' => $environment->field_cache_backend->plugin_id,
      ],
      'field_shp_domain' => $this->environmentService->getUniqueDomainForSite($site, $demoted_term),
      'field_shp_path' => $site->field_shp_path->value,
      'field_shp_cron_jobs' => $environment->field_shp_cron_jobs->getValue(),
      'field_shp_env_vars' => array_filter($environment->field_shp_env_vars->getValue(), function ($env_var) use ($ignore_env_vars) {
        return !in_array($env_var['key'], $ignore_env_vars, TRUE);
      }),
      'field_shp_cpu_limit' => $environment->field_shp_cpu_limit->value,
      'field_shp_cpu_request' => $environment->field_shp_cpu_request->value,
      'field_shp_memory_limit' => $environment->field_shp_memory_limit->value,
      'field_shp_memory_request' => $environment->field_shp_memory_request->value,
      'field_max_replicas' => $environment->field_max_replicas->value,
      'field_min_replicas' => $environment->field_min_replicas->value,
      'field_shp_secrets' => $environment->field_shp_secrets->value,
      'field_skip_db_prepop' => TRUE,
      // Set a flag that isn't saved, but can be checked in presave hooks.
      'shp_sync_environment' => TRUE,
    ]);
    $new_environment->save();
    $result = $this->orchestrationProvider->syncEnvironments(
      $site->id(),
      $environment->id(),
      $new_environment->id(),
    );

    return $result ? $new_environment : FALSE;
  }

  /**
   * Apply alterations to entity operations.
   *
   * @param array $operations
   *   List of operations.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to apply operations to.
   */
  public function operationsLinksAlter(array &$operations, EntityInterface $entity) {
    $site = $entity->field_shp_site->target_id;
    $environment = $entity->id();

    $modal_attributes = [
      'class' => ['button', 'use-ajax'],
      'data-dialog-type' => 'modal',
      'data-dialog-options' => Json::encode([
        'width' => '50%',
        'height' => '50%',
      ]),
    ];
    $operations['backup'] = [
      'title' => $this->t('Backup'),
      'weight' => 1,
      'url' => Url::fromRoute('shp_backup.environment-backup-form', [
        'site' => $site,
        'environment' => $environment,
      ]),
      'attributes' => $modal_attributes,
    ];

    // Allow restores on non-prod environments.
    if (!$this->environmentType->isPromotedEnvironment($entity)) {
      $operations['restore'] = [
        'title'      => $this->t('Restore'),
        'weight'     => 2,
        'url'        => Url::fromRoute('shp_backup.environment-restore-form', [
          'site'        => $site,
          'environment' => $environment,
        ]),
        'attributes' => $modal_attributes,
      ];
    }
    // Allow upgrade on all environments.
    $operations['upgrade'] = [
      'title'      => $this->t('Upgrade'),
      'weight'     => 2,
      'url'        => Url::fromRoute('shp_backup.environment-upgrade-form', [
        'site'        => $site,
        'environment' => $environment,
      ]),
      'attributes' => $modal_attributes,
    ];
  }

}
