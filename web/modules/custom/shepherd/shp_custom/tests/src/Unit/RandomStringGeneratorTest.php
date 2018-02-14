<?php

namespace Drupal\Tests\shp_custom;

use Drupal\shp_custom\Service\RandomStringGenerator;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the RandomString service.
 *
 * @group ua
 * @group shepherd
 * @group shp_custom
 * @coversDefaultClass \Drupal\shp_custom\Service\RandomStringGenerator
 */
class RandomStringGeneratorTest extends UnitTestCase {

  protected $randomStringService;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->randomStringService = new RandomStringGenerator();
  }

  /**
   * Tests the generateString function.
   *
   * @covers ::generateString
   */
  public function testGenerateString() {
    // Function by default generates 20 length.
    $generated = $this->randomStringService->generateString();
    $this->assertEquals(20, strlen($generated));
    $this->assertRegExp('/[0-9]/', $generated);
    $this->assertRegExp('/[a-z]/', $generated);
    $this->assertNotRegExp('/[A-Z]/', $generated);

    $generated = $this->randomStringService->generateString(0);
    $this->assertEquals(0, strlen($generated));

    $generated = $this->randomStringService->generateString(1);
    $this->assertEquals(1, strlen($generated));

    $generated = $this->randomStringService->generateString(20000);
    $this->assertEquals(20000, strlen($generated));
    $this->assertRegExp('/[0-9]/', $generated);
    $this->assertRegExp('/[a-z]/', $generated);
    $this->assertNotRegExp('/[A-Z]/', $generated);

    $generated = $this->randomStringService->generateString(100, RandomStringGenerator::NUMERIC);
    $this->assertRegExp('/[0-9]/', $generated);
    $this->assertNotRegExp('/[a-zA-Z]/', $generated);

    $generated = $this->randomStringService->generateString(100, RandomStringGenerator::LOWERCASE);
    $this->assertRegExp('/[a-z]/', $generated);
    $this->assertNotRegExp('/[0-9A-Z]/', $generated);

    $generated = $this->randomStringService->generateString(100, RandomStringGenerator::UPPERCASE);
    $this->assertRegExp('/[A-Z]/', $generated);
    $this->assertNotRegExp('/[0-9a-z]/', $generated);

    $generated = $this->randomStringService->generateString(100, RandomStringGenerator::SPECIAL);
    $this->assertRegExp('/[!@#$%^&*()]/', $generated);
    $this->assertNotRegExp('/[0-9a-zA-Z]/', $generated);

  }

  /**
   * Tests the generatePassword function.
   *
   * @covers ::generatePassword
   */
  public function testGeneratePassword() {
    // Function by default generates 30 length.
    $generated = $this->randomStringService->generatePassword();
    $this->assertEquals(20, strlen($generated));
    $this->assertRegExp('/[0-9a-zA-Z]/', $generated);

    $generated = $this->randomStringService->generatePassword(0);
    $this->assertEquals(0, strlen($generated));

    $generated = $this->randomStringService->generatePassword(1);
    $this->assertEquals(1, strlen($generated));

    $generated = $this->randomStringService->generatePassword(20000);
    $this->assertEquals(20000, strlen($generated));
    $this->assertRegExp('/[0-9a-zA-Z]/', $generated);
  }

}
