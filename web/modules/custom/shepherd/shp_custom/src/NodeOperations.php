<?php

namespace Drupal\shp_custom;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\node\NodeInterface;
use Drupal\shp_backup\Service\Backup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hook bridges for node operations
 */
class NodeOperations implements ContainerInjectionInterface {

  /**
   * The backup service.
   *
   * @var \Drupal\shp_backup\Service\Backup
   */
  protected $backupService;

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * NodeOperations constructor.
   *
   * @param \Drupal\shp_backup\Service\Backup $backupService
   *   The backup service.
   * @param \Drupal\Core\Entity\EntityStorageInterface $nodeStorage
   *   The node storage.
   */
  public function __construct(Backup $backupService, EntityStorageInterface $nodeStorage) {
    $this->backupService = $backupService;
    $this->nodeStorage = $nodeStorage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('shp_backup.backup'),
      $container->get('entity_type.manager')->getStorage('node')
    );
  }

  /**
   * Hook bridge for shp_custom_node_update.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   */
  public function nodeUpdate(NodeInterface $node) {
    if (strpos($node->bundle(), 'shp') === FALSE) {
      return NULL;
    }

    switch ($node->getType()) {
      case 'shp_site':
        if ($node->moderation_state->value == 'archived') {
          $environments = $this->nodeStorage->getQuery()
            ->condition('type', 'shp_environment')
            ->condition('field_shp_site', $node->id())
            ->execute();

          foreach ($this->nodeStorage->loadMultiple($environments) as $environment) {
            // Archived environments will already have a backup (see below).
            if ($environment->moderation_state->value === 'archived') {
              continue;
            }
            $this->backupService->createBackup($node, $environment);

            // @todo Shepherd: Need to queue to enable this part.
            //if (!$result = $orchestration_provider_plugin->archivedEnvironment($entity->id())) {
            //  return $result;
            //}
          }
        }
        break;

      case 'shp_environment':
        if ($node->moderation_state->value === 'archived') {
          $site = $node->field_shp_site->entity;
          $this->backupService->createBackup($site, $node);

          // @todo Shepherd: Need to queue to enable this part. I.e. backup must complete first.
          //$result = $orchestration_provider_plugin->archivedEnvironment($entity->id());
          shp_custom_invalidate_site_cache($node);
        }
        // @todo Shepherd: Add new environment to reverse proxy.
        // @todo Shepherd: Published environments should trigger re-deploy.
        // @todo Shepherd: Move state transitioning to environment?
        break;
    }
  }

}
