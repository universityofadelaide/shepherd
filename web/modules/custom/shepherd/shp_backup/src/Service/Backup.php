<?php

namespace Drupal\shp_backup\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\shp_orchestration\OrchestrationProviderPluginManagerInterface;
use Drupal\shp_orchestration\Service\ActiveJobManager;
use Drupal\taxonomy\Entity\Term;
use Drupal\token\TokenInterface;
use Drupal\views\Views;

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
    $project = $node_storage->load($site->field_shp_project->target_id);

    if (empty($site) || empty($environment) || empty($project)) {
      // User has deleted something, we can't proceed, so return FALSE early
      // & Un-publish the backup entry as a simple indicator that it borked.
      \Drupal::logger('shp_backup')->error('Unable to create backup, one of the following nodes is missing. Site: %site, Environment: %environment, Project: %project', [
        '%site' => $backup->field_shp_site->target_id,
        '%environment' => $backup->field_shp_environment->target_id,
        '%project' => (isset($site) ? $site->field_shp_project->target_id : ''),
      ]);
      $backup->set('status', 0);
      $backup->save();
      return FALSE;
    }

    $project_name = $project->title->value;

    $backup_command = str_replace(["\r\n", "\n", "\r"], ' && ', trim($this->config->get('backup_command')));
    $backup_command = $this->token->replace($backup_command, ['backup' => $backup]);

    $result = $this->orchestrationProvider->backupEnvironment(
      $project_name,
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
   *   True on success.
   */
  public function restore(NodeInterface $backup, NodeInterface $environment) {
    $node_storage = $this->entityTypeManager->getStorage('node');
    $site = $node_storage->load($backup->field_shp_site->target_id);
    $project = $node_storage->load($site->field_shp_project->target_id);
    $project_name = $project->title->value;

    if (empty($site) || empty($environment) || empty($project)) {
      // User has deleted something, we can't proceed, so return FALSE early.
      \Drupal::logger('shp_restore')->error('Unable to restore from backup, one of the following nodes is missing. Site: %site, Environment: %environment, Project: %project', [
        '%site' => $backup->field_shp_site->target_id,
        '%environment' => $environment,
        '%project' => (isset($site) ? $site->field_shp_project->target_id : ''),
      ]);
      return FALSE;
    }

    $restore_command = str_replace(["\r\n", "\n", "\r"], ' && ', trim($this->config->get('restore_command')));
    $restore_command = $this->token->replace($restore_command, ['backup' => $backup]);

    $result = $this->orchestrationProvider->restoreEnvironment(
      $project_name,
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
      case 'shp_restore':
        // If we can't find the provider job, then its probably been deleted, move along.
        if (!$provider_job = $this->orchestrationProvider->getJob($job->name)) {
          return TRUE;
        }
        // @todo Fix OpenShift specific structure leaking here.
        $complete = $provider_job['status']['conditions'][0]['type'] == 'Complete'
          && $provider_job['status']['conditions'][0]['status'] == 'True';

        // @todo Check if job successful?
        // $succeeded = $provider_job['status']['succeeded'] == '1';
        break;
    }

    return $complete;
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
      'url' => Url::fromRoute('shp_backup.environment-backup-form',
        ['site' => $site, 'environment' => $environment]),
      // Render form in a modal window.
      'attributes' => [
        'class' => ['button', 'use-ajax'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode(['width' => '50%', 'height' => '50%']),
      ],
    ];

    if ($environment_term = Term::load($entity->field_shp_environment_type->target_id)) {
      if (!$environment_term->field_shp_protect->value) {
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
            'data-dialog-options' => Json::encode([
              'width'  => '50%',
              'height' => '50%',
            ]),
          ],
        ];
      }
    }
  }

}
