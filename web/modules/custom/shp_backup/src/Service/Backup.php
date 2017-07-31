<?php

namespace Drupal\shp_backup\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\node\Entity\Node;
use Drupal\shp_orchestration\Exception\OrchestrationProviderNotConfiguredException;
use Drupal\node\NodeInterface;
use Drupal\token\Token;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Provides a service for accessing the backups.
 *
 * @package Drupal\shp_backup
 */
class Backup {

  private $config;

  /**
   * @var \Drupal\Core\Config\ConfigFactory
   *   Used to retrieve config.
   */
  protected $configFactory;

  /**
   * @var \Drupal\token\Token
   *   Used to expand tokens from config into usable strings.
   */
  protected $token;

  /**
   * The job runner is used to trigger the backup process.
   *
   * @var \GuzzleHttp\Client
   */
  protected $jobRunner;

  /**
   * Backup constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\token\Token $token
   *   Token service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Token $token) {
    $this->configFactory = $config_factory;
    $this->config = $this->configFactory->get('shp_backup.settings');
    $this->token = $token;
  }

  public function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('token')
    );
  }

  /**
   * Gets a sorted list of backups for all the environments of a site.
   *
   * @param NodeInterface $site
   *   Site node to retrieve the list of backups for.
   *
   * @return array
   *   An array of backup folders.
   */
  public function getAll(NodeInterface $site) {

    $view = Views::getView('shp_site_backups');
    $view->setArguments(['site' => $site->id()]);
    $view->render();
    $backups = $view->result;

    return $backups;
  }


  /**
   * Create a backup node for a given site and environment
   *
   * @param NodeInterface $site
   *   The site to create the backup node for.
   * @param NodeInterface $environment
   *   The environment to create the backup node for.
   * @param string $title
   *   The title to use for the backup node.
   * @param bool $perform_backup
   *   Execute a backup after creating the node?
   *
   * @return bool
   */
  public function createBackupNode(NodeInterface $site, NodeInterface $environment, $title = NULL, bool $perform_backup = FALSE) {
    if (!isset($title)) {
      $config = \Drupal::config('shp_backup.settings');
      $title = $this->token->replace($config->get('backup_title'), ['environment' => $environment]);
    }
    // Create a backup node with most values.
    $backup_node = Node::create([
      'type'                     => 'shp_backup',
      'langcode'                 => 'en',
      'uid'                      => \Drupal::currentUser()->id(),
      'status'                   => 1,
      'title'                    => $title,
      'field_shp_backup_path'    => [['value' => '']],
      'field_shp_site'           => [['target_id' => $site->id()]],
      'field_shp_environment'    => [['target_id' => $environment->id()]],
    ]);
    $backup_node->save();

    if ($perform_backup) {
      return $this->createBackup($backup_node);
    }
  }

  /**
   * Create a backup for a backup node.
   *
   * @param NodeInterface $backup
   *   The environment to backup.
   *
   * @return bool
   */
  public function createBackup(NodeInterface $backup) {
    try {
      /** @var \Drupal\shp_orchestration\OrchestrationProviderInterface $orchestration_provider_plugin */
      $orchestration_provider_plugin = \Drupal::service('plugin.manager.orchestration_provider')
        ->getProviderInstance();
    }
    catch (OrchestrationProviderNotConfiguredException $e) {
      drupal_set_message($e->getMessage(), 'warning');
      return FALSE;
    }

    $site = Node::load($backup->field_shp_site->target_id);
    $environment = Node::load($backup->field_shp_environment->target_id);
    $distribution = Node::load($site->field_shp_distribution->target_id);
    $distribution_name = $distribution->title->value;

    // Store the path for this backup in the backup node.
    $backup->set('field_shp_backup_path', $this->token->replace('[shepherd:backup-path]', ['backup' => $backup]));
    $backup->save();

    $backup_command = str_replace([ "\r\n", "\n", "\r" ], ' && ', trim($this->config->get('backup_command')));
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
   * @param NodeInterface $backup
   *   The backup to restore.
   * @param NodeInterface $environment
   *   The environment to restore.
   *
   * @return bool
   */
  public function restoreBackup(NodeInterface $backup, NodeInterface $environment) {
    try {
      /** @var \Drupal\shp_orchestration\OrchestrationProviderInterface $orchestration_provider_plugin */
      $orchestration_provider_plugin = \Drupal::service('plugin.manager.orchestration_provider')
        ->getProviderInstance();
    }
    catch (OrchestrationProviderNotConfiguredException $e) {
      drupal_set_message($e->getMessage(), 'warning');
      return FALSE;
    }

    $site = Node::load($backup->field_shp_site->target_id);
    $distribution = Node::load($site->field_shp_distribution->target_id);
    $distribution_name = $distribution->title->value;

    $restore_command = str_replace([ "\r\n", "\n", "\r" ], ' && ', trim($this->config->get('restore_command')));
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
