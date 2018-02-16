<?php

namespace Drupal\Tests\shp_custom;

use Drupal\shp_custom\Service\StringGenerator;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the RandomString service.
 *
 * @group shepherd
 * @group shp_custom
 * @coversDefaultClass \Drupal\shp_custom\Service\StringGenerator
 */
class RandomStringGeneratorTest extends UnitTestCase {

  /**
   * String Generator to be tested.
   *
   * @var \Drupal\shp_custom\Service\StringGenerator
   *   String generator.
   */
  protected $stringGeneratorService;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->stringGeneratorService = new StringGenerator();
  }

  /**
   * Tests the generateRandomString function.
   *
   * @covers ::generateRandomString
   */
  public function testGenerateRandomString() {
    // Function by default generates 20 length.
    $generated = $this->stringGeneratorService->generateRandomString();
    $this->assertEquals(20, strlen($generated));
    $this->assertRegExp('/[0-9]/', $generated);
    $this->assertRegExp('/[a-z]/', $generated);
    $this->assertNotRegExp('/[A-Z]/', $generated);

    $generated = $this->stringGeneratorService->generateRandomString(0);
    $this->assertEquals(0, strlen($generated));

    $generated = $this->stringGeneratorService->generateRandomString(1);
    $this->assertEquals(1, strlen($generated));

    $generated = $this->stringGeneratorService->generateRandomString(20000);
    $this->assertEquals(20000, strlen($generated));
    $this->assertRegExp('/[0-9]/', $generated);
    $this->assertRegExp('/[a-z]/', $generated);
    $this->assertNotRegExp('/[A-Z]/', $generated);

    $generated = $this->stringGeneratorService->generateRandomString(100, StringGenerator::NUMERIC);
    $this->assertRegExp('/[0-9]/', $generated);
    $this->assertNotRegExp('/[a-zA-Z]/', $generated);

    $generated = $this->stringGeneratorService->generateRandomString(100, StringGenerator::LOWERCASE);
    $this->assertRegExp('/[a-z]/', $generated);
    $this->assertNotRegExp('/[0-9A-Z]/', $generated);

    $generated = $this->stringGeneratorService->generateRandomString(100, StringGenerator::UPPERCASE);
    $this->assertRegExp('/[A-Z]/', $generated);
    $this->assertNotRegExp('/[0-9a-z]/', $generated);

    $generated = $this->stringGeneratorService->generateRandomString(100, StringGenerator::SPECIAL);
    $this->assertRegExp('/[!@#$%^&*()]/', $generated);
    $this->assertNotRegExp('/[0-9a-zA-Z]/', $generated);
  }

  /**
   * Tests the generateRandomPassword function.
   *
   * @covers ::generateRandomPassword
   */
  public function testGenerateRandomPassword() {
    // Function by default generates 20 length.
    $generated = $this->stringGeneratorService->generateRandomPassword();
    $this->assertEquals(20, strlen($generated));
    $this->assertRegExp('/[0-9a-zA-Z!@#$%^&*()]/', $generated);
    // Check that we aren't generating identical passwords..
    $this->assertNotEquals($generated, $this->stringGeneratorService->generateRandomPassword());

    $generated = $this->stringGeneratorService->generateRandomPassword(0);
    $this->assertEquals(0, strlen($generated));

    $generated = $this->stringGeneratorService->generateRandomPassword(1);
    $this->assertEquals(1, strlen($generated));

    $generated = $this->stringGeneratorService->generateRandomPassword(20000);
    $this->assertEquals(20000, strlen($generated));
    $this->assertRegExp('/[0-9a-zA-Z!@#$%^&*()]/', $generated);
  }

}
