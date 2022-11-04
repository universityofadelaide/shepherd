<?php

declare(strict_types=1);

namespace Drupal\shp_service_accounts\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\shp_service_account\Exception\NoServiceAccountException;
use Drupal\shp_service_account\Exception\SiteNotFoundException;
use Drupal\shp_service_accounts\Entity\ServiceAccount;

/**
 * Implement a simple random selector for the service account selection.
 */
class ServiceAccounts implements ServiceAccountsInterface {

  /**
   * Storage for the injected variable.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getServiceAccount($siteId): ?ServiceAccount {
    /** @var \Drupal\node\Entity\Node $site */
    $site = $this->entityTypeManager->getStorage('node')->load($siteId);

    if (!$site) {
      throw new SiteNotFoundException("Site not found.");
    }

    if (!isset($site->field_shp_service_account->value)) {
      $serviceAccount = $this->getLowestServiceAccount();
    }
    else {
      $serviceAccount = $this->getServiceAccountByName($site->field_shp_service_account->value);
    }

    return $serviceAccount;
  }

  /**
   * {@inheritdoc}
   */
  public function getServiceAccounts(): ?array {
    $serviceAccountList = $this->entityTypeManager->getStorage('service_account')->loadByProperties([
      'status' => TRUE,
    ]);

    $entries = count($serviceAccountList);
    if ($entries === 0) {
      throw new NoServiceAccountException("No service accounts defined.");
    }

    return $serviceAccountList;
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
    $serviceAccountList = $this->getServiceAccounts();

    /** @var \Drupal\shp_service_accounts\Entity\ServiceAccount */
    return array_values($serviceAccountList)[random_int(0, count($serviceAccountList) - 1)];
  }

  /**
   * Retrieve the list of service accounts and return the least used one.
   *
   * @throws \Exception
   *   If there are no service accounts defined.
   *
   * @return \Drupal\shp_service_accounts\Entity\ServiceAccount|null
   *   The ServiceAccount or NULL
   */
  public function getLowestServiceAccount(): ?ServiceAccount {
    $serviceAccountDist = [];
    $serviceAccountList = $this->getServiceAccounts();

    foreach ($serviceAccountList as $serviceAccount) {
      $query = $this->entityTypeManager->getStorage('node')
        ->getQuery()
        ->accessCheck(FALSE);

      $query->condition('type', 'shp_site');
      $query->condition('field_shp_service_account', $serviceAccount->label());
      $serviceAccountDist[$serviceAccount->label()] = count($query->execute());
    }

    asort($serviceAccountDist);

    $array = array_keys($serviceAccountDist);
    $lowestAccount = reset($array);

    return $this->getServiceAccountByName($lowestAccount);
  }

  /**
   * {@inheritdoc}
   */
  public function getServiceAccountByName($serviceAccountName): ?ServiceAccount {
    $serviceAccountList = $this->entityTypeManager->getStorage('service_account')->loadByProperties([
      'label' => $serviceAccountName,
      'status' => TRUE,
    ]);

    if (count($serviceAccountList) > 1) {
      throw new NoServiceAccountException("Multiple matching service accounts.");
    }

    /** @var \Drupal\shp_service_accounts\Entity\ServiceAccount */
    return reset($serviceAccountList) ?: NULL;
  }

}
