<?php

namespace Drupal\shp_content_types\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the submitted value is unique when combined with supplied fields.
 *
 * @Constraint(
 *   id = "ComboUniqueField",
 *   label = @Translation("ComboUniqueField", context = "Validation"),
 * )
 */
class ComboUniqueField extends Constraint {
  public $fields = [];
  public $message = '%value is not unique in combination with %fields.';

}
