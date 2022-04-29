<?php

namespace Drupal\Tests\shp_orchestration\Unit;

use Drupal\shp_orchestration\Event\OrchestrationEnvironmentEvent;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the OrchestrationEvents class constants.
 *
 * @group shepherd
 * @group shp_orchestration
 * @coversDefaultClass \Drupal\shp_orchestration\Event\OrchestrationEnvironmentEvent
 */
class OrchestrationEnvironmentEventTest extends UnitTestCase {

  /**
   * Orchestration provider interface mock.
   *
   * @var \Drupal\shp_orchestration\OrchestrationProviderInterface
   *   Orchestration provider interface.
   */
  protected $orchestrationProviderMock;

  /**
   * Mock deployment name.
   *
   * @var string
   *   Deployment name mock.
   */
  protected $deploymentNameMock;

  /**
   * Site node mock.
   *
   * @var \Drupal\node\NodeInterface
   *   Site node mock.
   */
  protected $siteMock;

  /**
   * Environment node mock.
   *
   * @var \Drupal\node\NodeInterface
   *   Environment mode mock.
   */
  protected $environmentMock;

  /**
   * Project node mock.
   *
   * @var \Drupal\node\NodeInterface
   *   Project node mock.
   */
  protected $projectMock;

  /**
   * Orchestration environment event.
   *
   * @var \Drupal\shp_orchestration\Event\OrchestrationEnvironmentEvent
   *  Orchestration environment event.
   */
  protected $orchestrationEvents;

  /**
   * Environment Variables used in tests.
   *
   * @var array
   *  Test Environment Variables.
   */
  protected $testEnvironmentVariables;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->orchestrationProviderMock = $this->getMockBuilder('Drupal\shp_orchestration\OrchestrationProviderInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $this->deploymentNameMock = 'MyTestDeployment';

    $this->siteMock = $this->getMockBuilder('Drupal\node\Entity\Node')
      ->onlyMethods(['id'])
      ->disableOriginalConstructor()
      ->getMock();
    $this->siteMock->expects($this->any())
      ->method('id')
      ->willReturn('Site node mock');

    $this->environmentMock = $this->getMockBuilder('Drupal\node\Entity\Node')
      ->onlyMethods(['id'])
      ->disableOriginalConstructor()
      ->getMock();
    $this->environmentMock->expects($this->any())
      ->method('id')
      ->willReturn('Environment node mock');

    $this->projectMock = $this->getMockBuilder('Drupal\node\Entity\Node')
      ->onlyMethods(['id'])
      ->disableOriginalConstructor()
      ->getMock();
    $this->projectMock->expects($this->any())
      ->method('id')
      ->willReturn('Project node mock');

    $this->testEnvironmentVariables = [
      'testvariable1' => 'variable',
      'testvariable2' => 'variable!',
      'testvariable3' => 'variable test',
      'testvariable4' => 'very variable',
    ];

    $this->orchestrationEvents = new OrchestrationEnvironmentEvent($this->orchestrationProviderMock, $this->deploymentNameMock, $this->siteMock, $this->environmentMock, $this->projectMock);
  }

  /**
   * Test getOrchestrationProvider function.
   *
   * @covers ::getOrchestrationProvider
   */
  public function testGetOrchestrationProvider() {
    // While it's an empty mock, it'll only pass if the object is the same type.
    $this->assertEquals($this->orchestrationProviderMock, $this->orchestrationEvents->getOrchestrationProvider());
  }

  /**
   * Test getDeploymentName function.
   *
   * @covers ::getDeploymentName
   */
  public function testGetDeploymentName() {
    $this->assertEquals('MyTestDeployment', $this->orchestrationEvents->getDeploymentName());
  }

  /**
   * Test getSite function.
   *
   * @covers ::getSite
   */
  public function testGetSite() {
    $this->assertEquals('Site node mock', $this->orchestrationEvents->getSite()->id());
  }

  /**
   * Test getEnvironment function.
   *
   * @covers ::getEnvironment
   */
  public function testGetEnvironment() {
    $this->assertEquals('Environment node mock', $this->orchestrationEvents->getEnvironment()->id());

  }

  /**
   * Test getProject function.
   *
   * @covers ::getProject
   */
  public function testGetProject() {
    $this->assertEquals('Project node mock', $this->orchestrationEvents->getProject()->id());
  }

  /**
   * Test setEnvironmentVariables and getEnvironmentVariables functions.
   *
   * @covers ::setEnvironmentVariables
   * @covers ::getEnvironmentVariables
   */
  public function testEnvironmentVariables() {
    $this->orchestrationEvents->setEnvironmentVariables($this->testEnvironmentVariables);
    $this->assertEquals($this->testEnvironmentVariables, $this->orchestrationEvents->getEnvironmentVariables());
  }

}
