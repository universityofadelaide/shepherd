<?php
/**
 * @file
 * Contains Drupal\ua_sm_custom\Service\User.
 */

namespace Drupal\ua_sm_custom\Service;

use Drupal\user\Entity\User as DrupalUser;

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

  /**
   * Populate users fields with attributes from LDAP.
   *
   * @param \Drupal\user\Entity\User $account
   */
  public function populateFieldsFromLdap(DrupalUser $account) {
    $uid = $account->name->value;
    $attributes = \Drupal::service('ua_ldap.ldap_user')->getAttributes($uid);
    $this->populateFields($account, $attributes);
    $account->save();
  }

  /**
   * Sets fields of account with attributes.
   *
   * @param $account the Drupal user account.
   * @param $attributes the attributes associated with the account.
   */
  protected function populateFields($account, $attributes) {
    // FYI: UAT LDAP shows a different mail attribute to PRD LDAP.
    $field_map = [
      'field_ua_user_preferred_name' => 'preferredname',
      'mail' => 'mail',
    ];

    foreach($field_map as $user_field => $ldap_field) {
      if (isset($attributes[$ldap_field])) {
        $account->set($user_field, reset($attributes[$ldap_field]));
      }
    }
  }

  /**
   * Provision a drupal user from an ldap user id.
   *
   * @param $uid The user id string.
   * @return \Drupal\Core\Entity\EntityInterface The drupal user account.
   */
  public function provision($uid) {
    $user_storage = \Drupal::entityTypeManager()->getStorage('user');
    $account = $user_storage->create([
      'name' => $uid,
      'status' => 1,
      'pass' => user_password(30),
    ]);
    $account->enforceIsNew();
    $account->save();
    return $account;
  }

}
