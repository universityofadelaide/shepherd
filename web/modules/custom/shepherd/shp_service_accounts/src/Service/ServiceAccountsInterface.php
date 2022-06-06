<?php

declare(strict_types=1);

namespace Drupal\shp_service_accounts\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\shp_service_accounts\Entity\ServiceAccount;

interface ServiceAccountsInterface {

  /**
   * Constuctor to store the injected variables.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The injected entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager);

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
  public function getServiceAccount($siteId): ?ServiceAccount;

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
  public function getRandomServiceAccount(): ?ServiceAccount;

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
  public function getServiceAccountByName($serviceAccountName): ?ServiceAccount;

}
