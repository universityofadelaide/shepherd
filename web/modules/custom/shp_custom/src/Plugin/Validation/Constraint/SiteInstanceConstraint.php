<?php

/**
 * @file
 * Contains \Drupal\shp_custom\Plugin\Validation\Constraint\SiteInstanceConstraint.
 */

namespace Drupal\shp_custom\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;


/**
 * Constraint checking if environment has reached the limit of site instances.
 *
 * @Constraint(
 *   id = "SiteInstance",
 *   label = @Translation("Limit number of site instances", context = "Validation"),
 *   type = "entity:node"
 * )
 */
class SiteInstanceConstraint extends Constraint {

  /**
   * Message shown when the instance limit is reached.
   *
   * @var string
   */
  public $messageLimit = 'The web server <em>%server</em> has reached the maximum limit of instances: %limit.';

  /**
   * The instance limit.
   *
   * @var int
   */
  public $limit;

  /**
   * Node entity you want to perform this validation on.
   *
   * @var string
   */
  public $entity;
  /**
   * Constructs a SiteInstanceConstraint instance.
   *
   * @param array $options
   *   Optional array of options.
   */
  public function __construct(array $options = NULL) {
    parent::__construct($options);
  }

}
