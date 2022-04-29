<?php

namespace Drupal\shp_custom\Service;

use Drupal\node\NodeInterface;

/**
 * Environment type service.
 *
 * @package Drupal\shp_custom\Service
 */
interface EnvironmentTypeInterface {

  /**
   * Determine whether an environment is promoted or now.
   *
   * @param \Drupal\node\NodeInterface $environment
   *   The environment.
   *
   * @return bool
   *   TRUE if this environment is promoted.
   */
  public function isPromotedEnvironment(NodeInterface $environment);

  /**
   * Load the term that is used for promoted environments (production).
   *
   * There can be only one promoted term.
   *
   * @todo Make configurable in UI.
   *
   * @return \Drupal\Core\Entity\EntityInterface|false
   *   The promoted term.
   */
  public function getPromotedTerm();

  /**
   * Load the term that is used for demoted environments (old production).
   *
   * There can be only one demoted term.
   *
   * @todo Make configurable in UI.
   *
   * @return \Drupal\Core\Entity\EntityInterface|false
   *   The demoted term.
   */
  public function getDemotedTerm();

}
