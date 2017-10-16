<?php

namespace Drupal\shp_content_types\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the Path constraint.
 */
class PathValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    foreach ($items as $item) {
      $path = $item->value;
      // If path doesn't match epic regex OR
      // (path is longer than 1 character AND the last character is a slash)...
      if (
        // Epic permissible path characters regex - note leading slash.
        // @see https://stackoverflow.com/questions/1547899/which-characters-make-a-url-invalid/1547940#1547940
        !preg_match('/^\/[a-zA-Z0-9-._~:?#@!$&%*+,;=\/\[\]\(\)\']*$/', $path) ||
        // Ensure last character is not slash for non-root paths.
        (strlen($path) > 1 && substr($path, -1) === '/')
      ) {
        $this->context->addViolation($constraint->message, ['%value' => $item->value]);
      }
    }
  }

}
