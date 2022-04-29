<?php

namespace Drupal\Tests\shepherd\Traits;

use Drupal\user\Entity\User;

/**
 * Provides functions for creating users during functional tests.
 */
trait UserCreationTrait {

  /**
   * Create a user with the administrator role.
   *
   * @param array $values
   *   An optional array of values to create the user with.
   *
   * @return \Drupal\user\Entity\User
   *   A user.
   */
  protected function createAdmin(array $values = []) {
    return $this->createUserAndCleanup(['administrator'], $values);
  }

  /**
   * Create a user and add it to the clean up array.
   *
   * @param array $roles
   *   An array of roles to add.
   * @param array $values
   *   An optional array of values to create the user with.
   *
   * @return \Drupal\user\Entity\User
   *   The user.
   */
  protected function createUserAndCleanup(array $roles, array $values = []) {
    $password = user_password();

    $values += [
      'name' => trim(preg_replace('/[^A-Za-z0-9 \-]/', '', $this->randomString())),
      'status' => TRUE,
      'pass' => $password,
    ];
    $values['mail'] = $values['name'] . '@example.com';
    $user = User::create($values);
    $user->passRaw = $password;
    foreach ($roles as $role) {
      $user->addRole($role);
    }
    $user->save();
    $this->markEntityForCleanup($user);
    return $user;
  }

}
