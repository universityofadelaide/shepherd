<?php

namespace Drupal\shp_content_types\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the submitted value is a valid path.
 *
 * @Constraint(
 *   id = "Path",
 *   label = @Translation("Path", context = "Validation"),
 * )
 */
class Path extends Constraint {
  public $message = '%value is not a valid path. First character must be a slash, last character must not be slash (unless path is root), and use only path-safe characters. See https://tools.ietf.org/html/rfc3986#section-2';

}
