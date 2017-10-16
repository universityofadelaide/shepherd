<?php

namespace Drupal\shp_custom\Service;

/**
 * Provides a random string service.
 *
 * @package Drupal\shp_custom
 */
class RandomString {

  /**
   * Generates a random string of the specified length.
   *
   * @param int $length
   *   The length of the string to return.
   * @return string
   *   The generated string.
   *
   */
  public function generate($length = 20) {
    $count = range(0, $length);
    $random_alpha_numeric = function () {
      $chars = array_merge(range('a', 'z'), range(0, 9));
      return $chars[array_rand($chars)];
    };
    return implode(array_map($random_alpha_numeric, $count));
  }

}
