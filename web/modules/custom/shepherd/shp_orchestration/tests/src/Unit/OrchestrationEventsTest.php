<?php

namespace Drupal\Tests\shp_orchestration\Unit;

use Drupal\shp_orchestration\Event\OrchestrationEvents;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the OrchestrationEvents class constants.
 *
 * @group shepherd
 * @group shp_orchestration
 * @coversDefaultClass \Drupal\shp_orchestration\Event\OrchestrationEvents
 */
class OrchestrationEventsTest extends UnitTestCase {

  /**
   * Orchestration Events class.
   *
   * @var \Drupal\shp_orchestration\Event\OrchestrationEvents
   *   Orchestration Events class.
   */
  protected $orchestrationEvents;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->orchestrationEvents = new OrchestrationEvents();
  }

  /**
   * Test the constants.
   */
  public function testConstants() {
    $setup_environment   = 'shp_orchestration.setup_environment';
    $created_environment = 'shp_orchestration.created_environment';
    $updated_environment = 'shp_orchestration.updated_environment';
    $deleted_environment = 'shp_orchestration.deleted_environment';

    $this->assertEquals($setup_environment, $this->orchestrationEvents::SETUP_ENVIRONMENT);
    $this->assertEquals($created_environment, $this->orchestrationEvents::CREATED_ENVIRONMENT);
    $this->assertEquals($updated_environment, $this->orchestrationEvents::UPDATED_ENVIRONMENT);
    $this->assertEquals($deleted_environment, $this->orchestrationEvents::DELETED_ENVIRONMENT);
  }

}
