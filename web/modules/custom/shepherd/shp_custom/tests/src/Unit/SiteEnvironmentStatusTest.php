<?php

namespace Drupal\Tests\shp_custom\Unit;

use Drupal\shp_custom\Plugin\views\field\SiteEnvironmentStatus;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the ActiveJobManagerService class.
 *
 * @group shepherd
 * @group shp_custom
 * @coversDefaultClass \Drupal\shp_custom\Plugin\views\field\SiteEnvironmentStatus
 */
class SiteEnvironmentStatusTest extends UnitTestCase {

  /**
   * The class to be tested.
   *
   * @var \Drupal\shp_custom\Plugin\views\field\SiteEnvironmentStatus
   *   Shepherd custom SiteEnvironmentStatus class.
   */
  protected $siteEnvironmentStatus;

  /**
   * Mocked ResultRow.
   *
   * @var \Drupal\views\ResultRow
   *  Mocked result row.
   */
  protected $resultRow;

  /**
   * Mocked shp_orchestration Status class.
   *
   * @var \Drupal\shp_orchestration\Service\Status
   *   Mocked shp_orchestration Status class.
   */
  protected $mockStatus;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $nodeInterface = $this->getMockBuilder('Drupal\node\NodeInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $this->resultRow = $this->getMockBuilder('Drupal\views\ResultRow')
      ->disableOriginalConstructor()
      ->getMock();
    $this->resultRow->_entity = $nodeInterface;

    $shpOrchestrationStatus = $this->getMockBuilder('Drupal\shp_orchestration\Service\Status')
      ->setMethods(['get'])
      ->disableOriginalConstructor()
      ->getMock();

    $shpOrchestrationStatus->expects($this->any())
      ->method('get')
      ->will($this->returnCallback(
        function () {
          return $this->mockStatus;
        }
      ));

    $this->siteEnvironmentStatus = new SiteEnvironmentStatus([], '', [], $shpOrchestrationStatus);
  }

  /**
   * Test the render method.
   *
   * @covers ::render
   */
  public function testRender() {
    // When running with available pods, 'Running'.
    $this->mockStatus = ['running' => TRUE, 'available_pods' => 1];

    $result = $this->siteEnvironmentStatus->render($this->resultRow);
    $this->assertArrayHasKey('environment_status', $result);
    $this->assertArrayHasKey('#plain_text', $result['environment_status']);
    $this->assertEquals('Running', $result['environment_status']['#plain_text']);

    $this->mockStatus = ['running' => TRUE, 'available_pods' => 10];
    $result = $this->siteEnvironmentStatus->render($this->resultRow);
    $this->assertArrayHasKey('environment_status', $result);
    $this->assertArrayHasKey('#plain_text', $result['environment_status']);
    $this->assertEquals('Running', $result['environment_status']['#plain_text']);

    // When running with 0 pods, 'Building'.
    $this->mockStatus = ['running' => TRUE, 'available_pods' => 0];
    $result = $this->siteEnvironmentStatus->render($this->resultRow);
    $this->assertArrayHasKey('environment_status', $result);
    $this->assertArrayHasKey('#plain_text', $result['environment_status']);
    $this->assertEquals('Building', $result['environment_status']['#plain_text']);

    // When not running, 0 pods, 'Stopped'.
    $this->mockStatus = ['running' => FALSE, 'available_pods' => 0];
    $result = $this->siteEnvironmentStatus->render($this->resultRow);
    $this->assertArrayHasKey('environment_status', $result);
    $this->assertArrayHasKey('#plain_text', $result['environment_status']);
    $this->assertEquals('Stopped', $result['environment_status']['#plain_text']);

    // When not running, but pods are up, 'Failed'.
    $this->mockStatus = ['running' => FALSE, 'available_pods' => 1];
    $result = $this->siteEnvironmentStatus->render($this->resultRow);
    $this->assertArrayHasKey('environment_status', $result);
    $this->assertArrayHasKey('#plain_text', $result['environment_status']);
    $this->assertEquals('Failed', $result['environment_status']['#plain_text']);
    $this->mockStatus = ['running' => FALSE, 'available_pods' => 10];
    $result = $this->siteEnvironmentStatus->render($this->resultRow);
    $this->assertArrayHasKey('environment_status', $result);
    $this->assertArrayHasKey('#plain_text', $result['environment_status']);
    $this->assertEquals('Failed', $result['environment_status']['#plain_text']);
  }

}
