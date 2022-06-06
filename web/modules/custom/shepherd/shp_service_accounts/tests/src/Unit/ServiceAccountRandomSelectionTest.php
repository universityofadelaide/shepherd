<?php

namespace Drupal\Tests\shp_service_accounts\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\shp_service_accounts\Entity\ServiceAccount;
use Drupal\shp_service_accounts\Service\ServiceAccounts;
use Drupal\Tests\UnitTestCase;

class ServiceAccountRandomSelectionTest extends UnitTestCase {

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityTypeManager;

  /**
   * A Mock for the service accounts.
   *
   * @var \Drupal\shp_service_accounts\Service\ServiceAccounts
   */
  protected $serviceAccounts;

  /**
   * The modules to enable.
   *
   * @var string[]
   */
  public static $modules = [
    'shp_service_accounts',
    'shp_orchestration',
    'shp_custom',
    'serialization',
  ];

  /**
   * Setup function for the test.
   */
  public function setUp() {
    parent::setUp();

    // Create a mock EntityTypeManager object.
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    for ($i = 0; $i <= 4; $i++) {
      $serviceAccounts[$i] = new ServiceAccount([
        'id' => $i,
        'title' => 'Doesnt matter',
      ], 'service_account');
    }

    // Mock the User storage layer to create a new user.
    $entity_storage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');
    $entity_storage->expects($this->any())
      ->method('loadByProperties')
      ->will($this->returnValue($serviceAccounts));
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->will($this->returnValue($entity_storage));

    $this->serviceAccounts = new ServiceAccounts($this->entityTypeManager);
  }

  /**
   * Test that the random selection of service accounts works.
   */
  public function testRandomSelection() {
    $results = [];

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

}
