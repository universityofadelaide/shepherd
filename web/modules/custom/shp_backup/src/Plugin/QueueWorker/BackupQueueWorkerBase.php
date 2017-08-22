<?php

namespace Drupal\shp_backup\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\shp_backup\Service\Backup;
use Drupal\shp_orchestration\Service\ActiveJobManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class BackupQueueWorkerBase.
 */
abstract class BackupQueueWorkerBase extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * The backup service.
   *
   * @var \Drupal\shp_backup\Service\Backup
   */
  protected $backup;

  /**
   * The active job manager service.
   *
   * @var \Drupal\shp_orchestration\Service\ActiveJobManager
   */
  protected $activeJobManager;

  /**
   * Creates a new NodePublishBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_storage
   *   The node storage.
   * @param \Drupal\shp_backup\Service\Backup $backup
   *   The backup service.
   * @param \Drupal\shp_orchestration\Service\ActiveJobManager $activeJobManager
   *   The active job manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityStorageInterface $node_storage, Backup $backup, ActiveJobManager $activeJobManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->nodeStorage = $node_storage;
    $this->backup = $backup;
    $this->activeJobManager = $activeJobManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.manager')->getStorage('node'),
      $container->get('shp_backup.backup'),
      $container->get('shp_orchestration.active_job_manager')
    );
  }

  /**
   * Get the job name from the response.
   */
  public function getJobName($response_body) {
    // @todo Move to more generic location.
    if (array_key_exists('metadata', $response_body) && array_key_exists('name', $response_body['metadata'])) {
      return $response_body['metadata']['name'];
    }
    return FALSE;
  }

  /**
   * Inform the active job manager that the job has a name.
   *
   * @param \stdClass $job
   *   The job.
   * @param array $response_body
   *   The response body to extract job name from.
   */
  public function setJobName(\stdClass $job, array $response_body) {
    if ($name = $this->getJobName($response_body)) {
      $job->name = $name;
      $this->activeJobManager->update($job);
    }
  }

}
