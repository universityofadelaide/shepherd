<?php

namespace Drupal\shp_content_types\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the Domain constraint.
 */
class DomainValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    foreach ($items as $item) {
      if (!preg_match('/^[a-z.-]*$/', $item->value)) {
        $this->context->addViolation($constraint->message, ['%value' => $item->value]);
      }
    }
  }

}
