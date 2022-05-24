<?php

declare(strict_types=1);

namespace Drupal\shp_service_accounts\Service;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\shp_service_accounts\Entity\ServiceAccount;

/**
 * Implement a simple random selector for the service account selection.
 */
class ServiceAccounts {

  /**
   * Storage for the injected variable.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected EntityTypeManager $entityTypeManager;

  /**
   * Constuctor to store the injected variables.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The injected entity type manager.
   */
  public function __construct(EntityTypeManager $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Retrieve the list of service accounts and return a random one.
   *
   * Random as otherwise we need to keep track of the most recently
   * used one, wrap around, etc.
   *
   * @throws \Exception
   *   If there are no service accounts defined.
   *
   * @return \Drupal\shp_service_accounts\Entity\ServiceAccount|null
   *   The ServiceAccount or NULL
   */
  public function getServiceAccount($siteId): ?ServiceAccount {
    /** @var \Drupal\node\Entity\Node $site */
    $site = $this->entityTypeManager->getStorage('node')->load($siteId);

    if (!$site) {
      throw new \Exception("Site not found.");
    }

    if (!isset($site->field_shp_service_account->value)) {
      $serviceAccount = $this->getRandomServiceAccount();
    }
    else {
      $serviceAccount = $this->getServiceAccountByName($site->field_shp_service_account->value);
    }

    return $serviceAccount;
  }

  /**
   * Retrieve the list of service accounts and return a random one.
   *
   * Random as otherwise we need to keep track of the most recently
   * used one, wrap around, etc.
   *
   * @throws \Exception
   *   If there are no service accounts defined.
   *
   * @return \Drupal\shp_service_accounts\Entity\ServiceAccount|null
   *   The ServiceAccount or NULL
   */
  public function getRandomServiceAccount(): ?ServiceAccount {
    $serviceAccountList = $this->entityTypeManager->getStorage('service_account')->loadByProperties([
      'status' => TRUE,
    ]);

    $entries = count($serviceAccountList);
    if ($entries == 0) {
      throw new \Exception("No service accounts defined.");
    }

    /** @var \Drupal\shp_service_accounts\Entity\ServiceAccount */
    $serviceAccount = array_values($serviceAccountList)[random_int(0, $entries - 1)];
    return $serviceAccount;
  }

  /**
   * Retrieve the list of service accounts and return a random one.
   *
   * Random as otherwise we need to keep track of the most recently
   * used one, wrap around, etc.
   *
   * @throws \Exception
   *   If there are no service accounts defined.
   *
   * @return \Drupal\shp_service_accounts\Entity\ServiceAccount|null
   *   The ServiceAccount or NULL
   */
  public function getServiceAccountByName($serviceAccountName): ?ServiceAccount {
    $serviceAccountList = $this->entityTypeManager->getStorage('service_account')->loadByProperties([
      'label' => $serviceAccountName,
      'status' => TRUE,
    ]);

    if (count($serviceAccountList) > 1) {
      throw new \Exception("Multiple matching service accounts.");
    }

    return reset($serviceAccountList);
  }

}
