<?php

namespace Drupal\shp_backup\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\shp_orchestration\OrchestrationProviderPluginManagerInterface;
use Drupal\shp_orchestration\Service\ActiveJobManager;
use Drupal\token\TokenInterface;

/**
 * Provides a service for accessing the backups.
 *
 * @package Drupal\shp_backup
 */
class Backup {

  use StringTranslationTrait;

  /**
   * Backup settings.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Used to retrieve config.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Used to expand tokens from config into usable strings.
   *
   * @var \Drupal\token\Token
   */
  protected $token;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The active job manager service.
   *
   * @var \Drupal\shp_orchestration\Service\ActiveJobManager
   */
  protected $activeJobManager;

  /**
   * The orchestration provider plugin manager.
   *
   * @var \Drupal\shp_orchestration\OrchestrationProviderInterface
   */
  protected $orchestrationProvider;

  /**
   * Backup constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\token\TokenInterface $token
   *   Token service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\shp_orchestration\Service\ActiveJobManager $activeJobManager
   *   Active job manager.
   * @param \Drupal\shp_orchestration\OrchestrationProviderPluginManagerInterface $pluginManager
   *   The orchestration provider plugin manager.
   */
  public function __construct(ConfigFactoryInterface $configFactory, TokenInterface $token, EntityTypeManagerInterface $entityTypeManager, ActiveJobManager $activeJobManager, OrchestrationProviderPluginManagerInterface $pluginManager) {
    $this->configFactory = $configFactory;
    $this->config = $this->configFactory->get('shp_backup.settings');
    $this->token = $token;
    $this->entityTypeManager = $entityTypeManager;
    $this->activeJobManager = $activeJobManager;
    $this->orchestrationProvider = $pluginManager->getProviderInstance();
  }

  /**
   * Gets a sorted list of backups for all the environments of a site.
   *
   * @param \Drupal\node\NodeInterface $site
   *   Site node to retrieve the list of backups for.
   *
   * @return \UniversityOfAdelaide\OpenShift\Objects\Backups\BackupList|bool
   *   The backup list if successful otherwise false.
   */
  public function getAll(NodeInterface $site) {
    return $this->orchestrationProvider->getBackupsForSite($site->id());
  }

  /**
   * Create a backup node for a given site and environment.
   *
   * @param \Drupal\node\NodeInterface $site
   *   The site the environment blongs to.
   * @param \Drupal\node\NodeInterface $environment
   *   The environment to create the backup node for.
   *
   * @return \UniversityOfAdelaide\OpenShift\Objects\Backups\Backup|bool
   *   The backup object, or false.
   */
  public function createBackup(NodeInterface $site, NodeInterface $environment) {
    $project = $site->field_shp_project->entity;
    $project_name = $project->title->value;

//    $backup_command = str_replace(["\r\n", "\n", "\r"], ' && ', trim($this->config->get('backup_command')));
//    $backup_command = $this->token->replace($backup_command, ['backup' => $backup]);

    $result = $this->orchestrationProvider->backupEnvironment(
      $site->id(),
      $environment->id()
    );

    return $result;
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
//    $project = $node_storage->load($site->field_shp_project->target_id);
//    $project_name = $project->title->value;

//    $restore_command = str_replace(["\r\n", "\n", "\r"], ' && ', trim($this->config->get('restore_command')));
//    $restore_command = $this->token->replace($restore_command, ['backup' => $backup_name]);

    $result = $this->orchestrationProvider->restoreEnvironment(
      $backup_name,
      $site->id(),
      $environment->id()
    );

    return $result;
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

    $operations['backup'] = [
      'title' => $this->t('Backup'),
      'weight' => 1,
      'url' => Url::fromRoute('shp_backup.environment-backup-form', ['site' => $site, 'environment' => $environment]),
      // Render form in a modal window.
      'attributes' => [
        'class' => ['button', 'use-ajax'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode(['width' => '50%', 'height' => '50%']),
      ],
    ];

    if (($environment_term = $entity->field_shp_environment_type->entity) && !$environment_term->field_shp_protect->value) {
      $operations['restore'] = [
        'title'      => $this->t('Restore'),
        'weight'     => 2,
        'url'        => Url::fromRoute('shp_backup.environment-restore-form', [
          'site'        => $site,
          'environment' => $environment,
        ]),
        // Render form in a modal window.
        'attributes' => [
          'class'               => ['button', 'use-ajax'],
          'data-dialog-type'    => 'modal',
          'data-dialog-options' => Json::encode(['width'  => '50%', 'height' => '50%']),
        ],
      ];
    }
  }

}
