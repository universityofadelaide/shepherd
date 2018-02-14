<?php

namespace Drupal\Tests\shp_custom;

use Drupal\shp_custom\Service\RandomString;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the RandomString service.
 *
 * @group ua
 * @group shepherd
 * @group shp_custom
 * @coversDefaultClass \Drupal\shp_custom\Service\RandomString
 */
class RandomStringTest extends UnitTestCase {

  protected $randomStringService;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->randomStringService = new RandomString();
  }

  /**
   * Tests the generate function.
   *
   * @covers ::generate
   */
  public function testGenerate() {
    // Function by default generates 20 length.
    $generated = $this->randomStringService->generate();
    $this->assertEquals(20, strlen($generated));
    $this->assertRegExp('/[0-9]/', $generated);
    $this->assertRegExp('/[a-z]/', $generated);
    $this->assertNotRegExp('/[A-Z]/', $generated);

    $generated = $this->randomStringService->generate(0);
    $this->assertEquals(0, strlen($generated));

    $generated = $this->randomStringService->generate(1);
    $this->assertEquals(1, strlen($generated));

    $generated = $this->randomStringService->generate(20000);
    $this->assertEquals(20000, strlen($generated));
    $this->assertRegExp('/[0-9]/', $generated);
    $this->assertRegExp('/[a-z]/', $generated);
    $this->assertNotRegExp('/[A-Z]/', $generated);
  }

}
