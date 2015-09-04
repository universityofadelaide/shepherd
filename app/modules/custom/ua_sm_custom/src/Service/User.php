<?php
/**
 * @file
 * Contains Drupal\ua_sm_custom\Service\User.
 */

namespace Drupal\ua_sm_custom\Service;

/**
 * Class User
 * @package Drupal\ua_sm_custom\Service
 */
class User {

  /**
   * Loads public keys of users.
   *
   * @param array $users
   *   The users to load keys from.
   *
   * @return array
   *   An array of keys.
   */
  public function loadKeys($users) {
    $keys = [];
    foreach ($users as $user) {
      $users_keys = $user->get('field_ua_sm_keys');
      foreach ($users_keys as $user_key) {
        $keys[] = reset($user_key->getValue());
      }
    }
    return $keys;
  }

}
