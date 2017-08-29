<?php

namespace Drupal\shp_backup\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\shp_orchestration\Exception\OrchestrationProviderNotConfiguredException;
use Drupal\shp_orchestration\OrchestrationProviderPluginManagerInterface;
use Drupal\shp_orchestration\Service\ActiveJobManager;
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

    try {
      $this->orchestrationProvider = $pluginManager->getProviderInstance();
    }
    catch (OrchestrationProviderNotConfiguredException $e) {
      drupal_set_message($e->getMessage(), 'warning');
    }
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
   *   The backup entity.
   */
  public function createNode(NodeInterface $environment, $title = NULL) {
    if (!isset($title)) {

      // @todo Inject the service.
      $config = \Drupal::config('shp_backup.settings');
      $title = $this->token->replace($config->get('backup_title'), ['environment' => $environment]);
    }
    // Create a backup node.
    $backup = Node::create([
      'type'                     => 'shp_backup',
      'langcode'                 => 'en',
      // @todo Inject the service.
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
    $node_storage = $this->entityTypeManager->getStorage('node');
    $site = $node_storage->load($backup->field_shp_site->target_id);
    $environment = $node_storage->load($backup->field_shp_environment->target_id);
    $distribution = $node_storage->load($site->field_shp_distribution->target_id);
    $distribution_name = $distribution->title->value;

    $backup_command = str_replace(["\r\n", "\n", "\r"], ' && ', trim($this->config->get('backup_command')));
    $backup_command = $this->token->replace($backup_command, ['backup' => $backup]);

    $result = $this->orchestrationProvider->backupEnvironment(
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
    $node_storage = $this->entityTypeManager->getStorage('node');
    $site = $node_storage->load($backup->field_shp_site->target_id);
    $distribution = $node_storage->load($site->field_shp_distribution->target_id);
    $distribution_name = $distribution->title->value;

    $restore_command = str_replace(["\r\n", "\n", "\r"], ' && ', trim($this->config->get('restore_command')));
    $restore_command = $this->token->replace($restore_command, ['backup' => $backup]);

    $result = $this->orchestrationProvider->restoreEnvironment(
      $distribution_name,
      $site->field_shp_short_name->value,
      $environment->id(),
      $environment->field_shp_git_reference->value,
      $restore_command
    );

    return $result;
  }

  /**
   * Check if a job has completed.
   *
   * @param \stdClass $job
   *   The job.
   *
   * @return bool
   *   True if finished, otherwise false.
   */
  public function isComplete(\stdClass $job) {
    // @todo Check backup and restore.
    $complete = FALSE;
    switch ($job->queueWorker) {
      case 'shp_backup':
        // @todo Fix OpenShift specific structure leaking here.
        $provider_job = $this->orchestrationProvider->getJob($job->name);
        $complete = $provider_job['status']['conditions'][0]['type'] == 'Complete'
          && $provider_job['status']['conditions'][0]['status'] == 'True';
        // $succeeded = $provider_job['status']['succeeded'] == '1';
        break;

      case 'shp_restore':

        break;
    }

    return $complete;
  }

}
