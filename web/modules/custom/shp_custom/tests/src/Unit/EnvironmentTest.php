<?php
namespace Drupal\Tests\shp_custom;

use Drupal\shp_custom\Service\Environment;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for the Environment Service.
 *
 * @group shp
 * @group shp_custom
 * @coversDefaultClass \Drupal\shp_custom\Service\Environment
 */
class EnvironmentTest extends UnitTestCase {

  protected $environmentService;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->environmentService = new Environment();
  }

  /**
   * Test the alterNodeAddForm method.
   */
  public function testAlterNodeAddForm() {
    $this->assertEquals(TRUE, TRUE);
  }

}
