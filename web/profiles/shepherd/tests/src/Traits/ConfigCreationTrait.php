<?php

namespace Drupal\Tests\shepherd\Traits;

use Drupal\shp_service_accounts\Entity\ServiceAccount;

trait ConfigCreationTrait {

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
