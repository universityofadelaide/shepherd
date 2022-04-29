<?php

namespace Drupal\shp_content_types\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ComboUniqueField constraint.
 */
class ComboUniqueFieldValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    if (!$item = $items->first()) {
      return;
    }
    $field_name = $items->getFieldDefinition()->getName();
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $items->getEntity();
    $entity_type_id = $entity->getEntityTypeId();
    $id_key = $entity->getEntityType()->getKey('id');

    // Find entities of the same type with the same values.
    $query = \Drupal::entityQuery($entity_type_id)
      // The id could be NULL, so we cast it to 0 in that case.
      ->condition($id_key, (int) $items->getEntity()->id(), '<>')
      ->condition('type', $entity->bundle())
      ->condition($field_name, $item->value);

    // Add fields to query that need to be unique in combination.
    foreach ($constraint->fields as $field) {
      $query->condition($field, $entity->get($field)->value);
    }
    $query
      ->accessCheck(TRUE)
      ->range(0, 1)
      ->count();

    $value_taken = (bool) $query->execute();

    if ($value_taken) {
      $this->context->addViolation(
        $constraint->message, [
          '%value' => $item->value,
          '%fields' => implode(', ', $constraint->fields),
        ]
      );
    }
  }

}
