<?php

namespace Drupal\shp_content_types\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the submitted value is a valid environment variable name.
 *
 * @Constraint(
 *   id = "EnvironmentVariableName",
 *   label = @Translation("EnvironmentVariableName", context = "Validation"),
 * )
 */
class EnvironmentVariableName extends Constraint {

  /**
   * Invalid environment variable name message.
   *
   * @var string
   */
  public $message = '%value is not a valid environment variable name. Must consist solely of uppercase letters, digits, and the \'_\' (underscore) and do not begin with a digit.';

}
