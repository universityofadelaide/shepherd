<?php

namespace Drupal\shp_content_types\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the submitted value is a valid domain.
 *
 * @Constraint(
 *   id = "Domain",
 *   label = @Translation("Domain", context = "Validation"),
 * )
 */
class Domain extends Constraint {
  public $message = '%value is not a valid domain. You may use only letters, numbers, dots (.) and dashes (-).';

}
