<?php

namespace Drupal\Tests\shp_orchestration\Unit;

use Drupal\shp_orchestration\OrchestrationProviderBase;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the OrchestrationEvents class constants.
 *
 * @group shepherd
 * @group shp_orchestration
 *
 * @coversDefaultClass \Drupal\shp_orchestration\OrchestrationProviderBase
 */
class OrchestrationNamingTest extends UnitTestCase {

  /**
   * Test the constants.
   *
   * @covers ::sanitise
   *
   * @dataProvider sanitiseInputData
   */
  public function testSanitise($input, $expected_output) {
    $this->assertEquals($expected_output, OrchestrationProviderBase::sanitise($input));
  }

  /**
   * Test input data for sanitise test.
   */
  public function sanitiseInputData() {
    return [
      ['ABC-123-fix-bug', 'abc-123-fix-bug'],
      ['ABC-123-under_score', 'abc-123-under_score'],
      ['feature/ABC-234-add-feature', 'feature-abc-234-add-feature'],
    ];
  }

}
