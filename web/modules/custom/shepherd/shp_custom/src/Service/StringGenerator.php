<?php

namespace Drupal\shp_custom\Service;

/**
 * Provides a random string service.
 *
 * @package Drupal\shp_custom
 */
class StringGenerator {

  const UPPERCASE = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  const LOWERCASE = 'abcdefghijklmnopqrstuvwxyz';
  const NUMERIC = '0123456789';
  const SPECIAL = '!@#$%^&*()';
  const ALL = self::UPPERCASE . self::LOWERCASE . self::NUMERIC . self::SPECIAL;

  /**
   * Generates a random string of the specified length, default 20.
   *
   * @param int $length
   *   The length of the string to return.
   * @param string $keyspace
   *   A string of all possible characters to select from.
   *
   * @return string|null
   *   The generated string. If length supplied is 0, will return empty string.
   */
  public function generateRandomString(int $length = 20, string $keyspace = self::LOWERCASE . self::NUMERIC) {
    return $this->randomStringProvider($length, $keyspace);
  }

  /**
   * Generates a password consisting of random characters, default 20.
   *
   * @param int $length
   *   The length of the string to return.
   * @param string $keyspace
   *   A string of all possible characters to select from.
   *
   * @return string
   *   The generated password.
   */
  public function generateRandomPassword(int $length = 20, string $keyspace = self::ALL) {
    return $this->randomStringProvider($length, $keyspace);
  }

  /**
   * Generate a random string, cryptographically secure.
   *
   * @param int $length
   *   How many characters do we want?
   * @param string $keyspace
   *   A string of all possible characters to select from.
   *
   * @return string
   *   Generated string.
   */
  protected function randomStringProvider(int $length, string $keyspace = self::ALL) {
    $string = '';

    $max = mb_strlen($keyspace, '8bit') - 1;
    for ($i = 0; $i < $length; ++$i) {
      $string .= $keyspace[random_int(0, $max)];
    }
    return $string;
  }

}
