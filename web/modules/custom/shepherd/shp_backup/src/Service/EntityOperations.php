<?php

namespace Drupal\shp_backup\Service;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\node\NodeInterface;
use Drupal\shp_orchestration\OrchestrationProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hook bridges for entity operations.
 */
class EntityOperations implements ContainerInjectionInterface {

  /**
   * The orchestration provider plugin.
   *
   * @var \Drupal\shp_orchestration\OrchestrationProviderInterface
   */
  protected $orchestrationProvider;

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * EntityOperations constructor.
   *
   * @param \Drupal\shp_orchestration\OrchestrationProviderInterface $orchestrationProvider
   *   The orchestration provider plugin.
   * @param \Drupal\Core\Entity\EntityStorageInterface $nodeStorage
   *   The node storage.
   */
  public function __construct(OrchestrationProviderInterface $orchestrationProvider, EntityStorageInterface $nodeStorage) {
    $this->orchestrationProvider = $orchestrationProvider;
    $this->nodeStorage = $nodeStorage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.orchestration_provider')->getProviderInstance(),
      $container->get('entity_type.manager')->getStorage('node')
    );
  }

  /**
   * Hook bridge for shp_backup_taxonomy_term_update.
   *
   * @param \Drupal\Core\Entity\EntityInterface $term
   *   The term.
   */
  public function taxonomyTermUpdate(EntityInterface $term) {
    // Only act on environment type terms.
    if ($term->bundle() !== 'shp_environment_types' || !$term->hasField('field_shp_backup_schedule')) {
      return;
    }

    $schedule = trim($term->field_shp_backup_schedule->value);
    $retention = trim($term->field_shp_backup_retention->value);
    $env_ids = $this->nodeStorage->getQuery()
      ->condition('field_shp_environment_type.target_id', $term->id())
      ->execute();
    if (empty($env_ids)) {
      return;
    }
    array_map(function (NodeInterface $environment) use ($schedule, $retention) {
      if (!$environment->field_shp_site->target_id) {
        return;
      }
      // If there's no schedule value, consider this a delete.
      if ($schedule) {
        $this->orchestrationProvider->environmentScheduleBackupUpdate($environment->field_shp_site->target_id, $environment->id(), $schedule, $retention);
      }
      else {
        $this->orchestrationProvider->environmentScheduleBackupDelete($environment->id());
      }
    }, $this->nodeStorage->loadMultiple($env_ids));
  }

}
