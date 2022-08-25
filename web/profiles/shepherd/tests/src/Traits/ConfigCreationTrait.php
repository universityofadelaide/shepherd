<?php

namespace Drupal\Tests\shepherd\Traits;

use Drupal\shp_service_accounts\Entity\ServiceAccount;

/**
 * Trait to make configuration creation easier.
 */
trait ConfigCreationTrait {

  /**
   * Provide a quick way to create a service account for testing.
   *
   * @param array $values
   *   Values to use to construct the config entity.
   *
   * @return \Drupal\shp_service_accounts\Entity\ServiceAccount
   *   Return the constructed, saved service account.
   */
  protected function createServiceAccount(array $values = []) {
    $values = $values + [
      'type' => 'service_account',
    ];
    $serviceAccount = ServiceAccount::create($values);
    $serviceAccount->save();
    $this->markEntityForCleanup($serviceAccount);

    return $serviceAccount;
  }

}
