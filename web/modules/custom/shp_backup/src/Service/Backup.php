<?php

namespace Drupal\shp_backup\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\shp_orchestration\Exception\OrchestrationProviderNotConfiguredException;
use Drupal\node\NodeInterface;
use Drupal\token\TokenInterface;
use Drupal\views\Views;

/**
 * Provides a service for accessing the backups.
 *
 * @package Drupal\shp_backup
 */
class Backup {

  private $config;

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
   * Backup constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\token\TokenInterface $token
   *   Token service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TokenInterface $token, EntityTypeManagerInterface $entityTypeManager) {
    $this->configFactory = $config_factory;
    $this->config = $this->configFactory->get('shp_backup.settings');
    $this->token = $token;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Gets a sorted list of backups for all the environments of a site.
   *
   * @param \Drupal\node\NodeInterface $site
   *   Site node to retrieve the list of backups for.
   *
   * @return array
   *   An array of backup folders.
   */
  public function getAll(NodeInterface $site) {

    // @todo watch out for access control. Use EQ instead.
    $view = Views::getView('shp_site_backups');
    $view->setArguments(['site' => $site->id()]);
    $view->render();
    $backups = $view->result;

    return $backups;
  }

  /**
   * Create a backup node for a given site and environment.
   *
   * @param \Drupal\node\NodeInterface $environment
   *   The environment to create the backup node for.
   * @param string $title
   *   The title to use for the backup node.
   *
   * @return \Drupal\Core\Entity\EntityInterface|static
   */
  public function createNode(NodeInterface $environment, $title = NULL) {
    if (!isset($title)) {
      $config = \Drupal::config('shp_backup.settings');
      $title = $this->token->replace($config->get('backup_title'), ['environment' => $environment]);
    }
    // Create a backup node.
    $backup = Node::create([
      'type'                     => 'shp_backup',
      'langcode'                 => 'en',
      'uid'                      => \Drupal::currentUser()->id(),
      'status'                   => 1,
      'title'                    => $title,
      'field_shp_backup_path'    => [['value' => '']],
      'field_shp_site'           => [['target_id' => $environment->field_shp_site->target_id]],
      'field_shp_environment'    => [['target_id' => $environment->id()]],
    ]);
    // Store the path for this backup in the backup node.
    $backup->set('field_shp_backup_path', $this->token->replace('[shepherd:backup-path]', ['backup' => $backup]));
    $backup->save();

    return $backup;
  }

  /**
   * Create a backup for a backup node.
   *
   * @param \Drupal\node\NodeInterface $backup
   *   The environment to backup.
   *
   * @return bool
   *   True on success.
   */
  public function create(NodeInterface $backup) {
    try {
      /** @var \Drupal\shp_orchestration\OrchestrationProviderInterface $orchestration_provider_plugin */
      $orchestration_provider_plugin = \Drupal::service('plugin.manager.orchestration_provider')
        ->getProviderInstance();
    }
    catch (OrchestrationProviderNotConfiguredException $e) {
      drupal_set_message($e->getMessage(), 'warning');
      return FALSE;
    }

    $node_storage = $this->entityTypeManager->getStorage('node');
    $site = $node_storage->load($backup->field_shp_site->target_id);
    $environment = $node_storage->load($backup->field_shp_environment->target_id);
    $distribution = $node_storage->load($site->field_shp_distribution->target_id);
    $distribution_name = $distribution->title->value;

    $backup_command = str_replace(["\r\n", "\n", "\r"], ' && ', trim($this->config->get('backup_command')));
    $backup_command = $this->token->replace($backup_command, ['backup' => $backup]);

    $result = $orchestration_provider_plugin->backupEnvironment(
      $distribution_name,
      $site->field_shp_short_name->value,
      $environment->id(),
      $environment->field_shp_git_reference->value,
      $backup_command
    );

    return $result;
  }

  /**
   * Restore a backup for a given instance.
   *
   * @param \Drupal\node\NodeInterface $backup
   *   The backup to restore.
   * @param \Drupal\node\NodeInterface $environment
   *   The environment to restore.
   *
   * @return bool
   *   True on success
   */
  public function restore(NodeInterface $backup, NodeInterface $environment) {
    try {
      /** @var \Drupal\shp_orchestration\OrchestrationProviderInterface $orchestration_provider_plugin */
      $orchestration_provider_plugin = \Drupal::service('plugin.manager.orchestration_provider')
        ->getProviderInstance();
    }
    catch (OrchestrationProviderNotConfiguredException $e) {
      drupal_set_message($e->getMessage(), 'warning');
      return FALSE;
    }

    $node_storage = $this->entityTypeManager->getStorage('node');
    $site = $node_storage->load($backup->field_shp_site->target_id);
    $distribution = $node_storage->load($site->field_shp_distribution->target_id);
    $distribution_name = $distribution->title->value;

    $restore_command = str_replace(["\r\n", "\n", "\r"], ' && ', trim($this->config->get('restore_command')));
    $restore_command = $this->token->replace($restore_command, ['backup' => $backup]);

    $result = $orchestration_provider_plugin->restoreEnvironment(
      $distribution_name,
      $site->field_shp_short_name->value,
      $environment->id(),
      $environment->field_shp_git_reference->value,
      $restore_command
    );

    return $result;
  }

}
