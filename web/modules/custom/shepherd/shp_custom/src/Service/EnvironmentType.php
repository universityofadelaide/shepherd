<?php

namespace Drupal\shp_custom\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Environment type service.
 *
 * @package Drupal\shp_custom\Service
 */
class EnvironmentType implements EnvironmentTypeInterface {

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Taxonomy term entity type.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $taxonomyTerm;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->taxonomyTerm = $this->entityTypeManager->getStorage('taxonomy_term');
  }

  /**
   * {@inheritdoc}
   */
  public function getPromotedTerm() {
    $ids = $this->taxonomyTerm->getQuery()
      ->condition('vid', 'shp_environment_types')
      ->condition('field_shp_protect', TRUE)
      ->execute();
    $terms = $this->taxonomyTerm->loadMultiple($ids);
    return reset($terms);
  }

  /**
   * Load the term that is used for demoted environments (old production).
   *
   * There can be only one demoted term.
   *
   * @todo: Make configurable in UI.
   *
   * @return \Drupal\Core\Entity\EntityInterface|false
   *   The demoted term.
   */
  public function getDemotedTerm() {
    $ids = $this->taxonomyTerm->getQuery()
      ->condition('vid', 'shp_environment_types')
      ->condition('field_shp_protect', FALSE)
      ->execute();
    $terms = $this->taxonomyTerm->loadMultiple($ids);
    return reset($terms);
  }

}
