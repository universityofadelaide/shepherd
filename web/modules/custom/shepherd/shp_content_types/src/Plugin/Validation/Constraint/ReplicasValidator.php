<?php

namespace Drupal\shp_content_types\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the Replicas constraint.
 */
class ReplicasValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    if (!$item = $items->first()) {
      return;
    }
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $items->getEntity();
    if (!$entity->hasField('field_max_replicas')) {
      return;
    }
    $min = $item->value;
    $max = $entity->field_max_replicas->value;

    if ($min > $max) {
      $this->context->addViolation(
        $constraint->message, [
          '%min' => $min,
          '%max' => $max,
        ]
      );
    }
  }

}
