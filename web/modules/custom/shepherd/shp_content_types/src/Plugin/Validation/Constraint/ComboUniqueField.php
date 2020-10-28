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

  /**
   * Fields which comprise the unique combination.
   *
   * @var array
   */
  public $fields = [];

  /**
   * Uniqueness error message.
   *
   * @var string
   */
  public $message = '%value is not unique in combination with %fields.';

}
