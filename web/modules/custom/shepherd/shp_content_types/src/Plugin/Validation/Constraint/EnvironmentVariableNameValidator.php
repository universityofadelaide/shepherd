<?php

namespace Drupal\shp_content_types\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the EnvironmentVariableName constraint.
 */
class EnvironmentVariableNameValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    foreach ($items as $item) {
      $env_var = $item->getValue();

      // Unwrap key from key/value field.
      $env_var_name = is_array($env_var) && array_key_exists('key', $env_var) ? $env_var['key'] : '';
      if (!preg_match('/^[A-Z_][A-Z_0-9]*$/', $env_var_name)) {
        $this->context->addViolation($constraint->message, ['%value' => $env_var_name]);
      }
    }
  }

}
