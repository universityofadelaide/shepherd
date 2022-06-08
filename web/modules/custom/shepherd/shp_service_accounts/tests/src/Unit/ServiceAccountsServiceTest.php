<?php

namespace Drupal\Tests\shp_service_accounts\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\shp_service_accounts\Entity\ServiceAccount;
use Drupal\shp_service_accounts\Service\ServiceAccounts;
use Drupal\Tests\UnitTestCase;

/**
 * Perform some basic tests on the service accounts service.
 */
class ServiceAccountsServiceTest extends UnitTestCase {

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * A Mock for the service accounts.
   *
   * @var \Drupal\shp_service_accounts\Service\ServiceAccounts
   */
  protected $serviceAccounts;

  /**
   * Storage for the temp service accounts.
   *
   * @var \Drupal\shp_service_accounts\Service\ServiceAccounts[]
   */
  protected array $serviceAccountList;

  /**
   * Setup function for the test.
   */
  public function setUp(): void {
    parent::setUp();

    // Create a mock EntityTypeManager object.
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    // Create a list of service accounts that can be retrieved.
    $this->buildServiceAccountList();

    // Create the service.
    $this->serviceAccounts = new ServiceAccounts($this->entityTypeManager);
  }

  /**
   * Test that the random selection of service accounts works.
   */
  public function testRandomSelection() {
    $results = [];

    // Mock the storage layer.
    $entity_storage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');
    $entity_storage->expects($this->any())
      ->method('loadByProperties')
      ->will($this->returnValue($this->serviceAccountList));
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->will($this->returnValue($entity_storage));

    for ($i = 0; $i <= 20; $i++) {
      $test = $this->serviceAccounts->getRandomServiceAccount();
      if (isset($results[$test->id()])) {
        $results[$test->id()]++;
      }
      else {
        $results[$test->id()] = 1;
      }
    }

    // Check at least 3 of the 5 spots used.
    $used = 0;
    for ($i = 0; $i <= 4; $i++) {
      if (isset($results[$i])) {
        $used++;
      }
    }

    $this->assertGreaterThan(3, $used);
  }

  /**
   * Test that a site with a service account is retrieved correctly.
   */
  public function testServiceAccountByName() {

    // Mock the storage layer.
    $entity_storage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');

    $entity_storage->expects($this->any())
      ->method('loadByProperties')
      ->will($this->returnValue([$this->serviceAccountList[2]]));
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->will($this->returnValue($entity_storage));

    $serviceAccount = $this->serviceAccounts->getServiceAccountByName('Doesnt matter 2');
    $this->assertEquals(2, $serviceAccount->id());
  }

  /**
   * Helper function to create dummy service accounts.
   */
  private function buildServiceAccountList() {
    // Build a list of service accounts for loadByProperties mock.
    for ($i = 0; $i <= 4; $i++) {
      $this->serviceAccountList[$i] = new ServiceAccount([
        'id' => $i,
        'title' => 'Doesnt matter ' . $i,
        'status' => TRUE,
      ], 'service_account');
    }
  }

}
