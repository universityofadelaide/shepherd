<?php

namespace Drupal\shp_custom\Service;

/**
 * Provides a password service.
 *
 * @package Drupal\shp_custom
 */
class Password {

  /**
   * Generates a password of 20 random alphanumeric characters.
   *
   * @return string
   *   The generated password.
   *
   * @todo This is not good. Let's replace with something vaguely crypto secure.
   */
  public function generate() {
    $count = range(0, 20);
    $random_alphanum = function ($num) {
      $chars = array_merge(range('A', 'Z'), range('a', 'z'), range(0, 9));
      return $chars[array_rand($chars)];
    };
    return implode(array_map($random_alphanum, $count));
  }

}
