<?php

namespace Drupal\shp_content_types\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that min replicas are less than max replicas.
 *
 * @Constraint(
 *   id = "Replicas",
 *   label = @Translation("Replicas", context = "Validation"),
 * )
 */
class Replicas extends Constraint {

  /**
   * Invalid path message.
   *
   * @var string
   */
  public $message = 'Min replicas (%min) must be less than Max replicas (%max)';

}
