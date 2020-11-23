<?php

namespace Drupal\Tests\shepherd\Traits;

use Drupal\taxonomy\Entity\Term;

/**
 * Provides functions for creating content during functional tests.
 */
trait TaxonomyCreationTrait {

  /**
   * Create a env type term and mark it for cleanup.
   *
   * @param array $values
   *   Optional key => values to assign to the term.
   *
   * @return \Drupal\taxonomy\Entity\Term
   *   A term.
   */
  protected function createEnvType(array $values = []) {
    $values += [
      'vid' => 'shp_environment_types',
      'name' => $this->randomMachineName(),
      'field_shp_base_domain' => 'http://' . strtolower($this->randomMachineName(16)) . '.lol/',
      'field_shp_protect' => FALSE,
      'field_shp_update_go_live' => FALSE,
    ];

    return $this->doCreateTerm($values);
  }

  /**
   * Create a term and mark it for cleanup.
   *
   * @param array $values
   *   Array of key => values to assign to the term.
   *
   * @return \Drupal\taxonomy\Entity\Term
   *   A term.
   */
  private function doCreateTerm(array $values) {
    /** @var \Drupal\taxonomy\Entity\Term $term */
    $term = Term::create($values);
    $term->save();

    $this->cleanupEntities[] = $term;

    return $term;
  }

}
