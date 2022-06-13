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
      $serviceAccount = $this->getRandomServiceAccount();
    }
    else {
      $serviceAccount = $this->getServiceAccountByName($site->field_shp_service_account->value);
    }

    return $serviceAccount;
  }

  /**
   * {@inheritdoc}
   */
  public function getRandomServiceAccount(): ?ServiceAccount {
    $serviceAccountList = $this->entityTypeManager->getStorage('service_account')->loadByProperties([
      'status' => TRUE,
    ]);

    $entries = count($serviceAccountList);
    if ($entries == 0) {
      throw new NoServiceAccountException("No service accounts defined.");
    }

    /** @var \Drupal\shp_service_accounts\Entity\ServiceAccount */
    $serviceAccount = array_values($serviceAccountList)[random_int(0, $entries - 1)];
    return $serviceAccount;
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
    $serviceAccount = reset($serviceAccountList);
    return $serviceAccount;
  }

}
