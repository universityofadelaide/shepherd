<?php

namespace Drupal\Tests\shp_database_provisioner\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\shp_custom\Service\Environment;
use Drupal\shp_custom\Service\StringGenerator;
use Drupal\shp_database_provisioner\Service\Provisioner;
use Drupal\shp_orchestration\OrchestrationProviderInterface;
use Drupal\shp_orchestration\OrchestrationProviderPluginManagerInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the OrchestrationEvents class constants.
 *
 * @group shepherd
 * @group shp_database_provisioner
 *
 * @coversDefaultClass \Drupal\shp_database_provisioner\Service\Provisioner
 */
class ProvisionerTest extends UnitTestCase {

  /**
   * Provisioner service.
   *
   * @var \Drupal\shp_database_provisioner\Service\Provisioner
   */
  protected $provisioner;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $orchestration_provider_manager = $this->prophesize(OrchestrationProviderPluginManagerInterface::class);
    $environment = $this->prophesize(Environment::class);
    $string_generator = $this->prophesize(StringGenerator::class);

    $orchestration_provider = $this->prophesize(OrchestrationProviderInterface::class);
    $orchestration_provider_manager->getProviderInstance()
      ->willReturn($orchestration_provider->reveal());

    $this->provisioner = new Provisioner(
      $config_factory->reveal(),
      $orchestration_provider_manager->reveal(),
      $environment->reveal(),
      $string_generator->reveal()
    );
  }

  /**
   * Test the constants.
   *
   * @covers ::createDatabase
   *
   * @dataProvider createDatabaseInputData
   */
  public function testCreateDatabase($input, $expected_output) {
    $dbMock = $this->getMockBuilder('mysqli')
      ->getMock();
    $dbMock->expects($this->once())
      ->method('prepare')
      ->with($expected_output);
    $this->provisioner->createDatabase($input, $dbMock);

  }

  /**
   * Test input data for sanitise test.
   */
  public function createDatabaseInputData() {
    return [
      ['abc123', 'CREATE DATABASE `abc123`'],
    ];
  }

  /**
   * Test the constants.
   *
   * @covers ::createUser
   *
   * @dataProvider createUserInputData
   */
  public function testCreateUser($database, $username, $password, $options, $expected_output) {
    $dbMock = $this->getMockBuilder('mysqli')
      ->getMock();
    $dbMock->expects($this->once())
      ->method('prepare')
      ->with($expected_output);
    $this->provisioner->createUser($database, $username, $password, $dbMock, $options);

  }

  /**
   * Test input data for sanitise test.
   */
  public function createUserInputData() {
    return [
      [
        'foo',
        'bar',
        'meh',
        '',
        'GRANT ALL PRIVILEGES ON `foo`.* TO `bar`@`%` IDENTIFIED BY \'meh\'',
      ],
      [
        'foo',
        'bar',
        'meh',
        'MAX_USER_CONNECTIONS 20',
        'GRANT ALL PRIVILEGES ON `foo`.* TO `bar`@`%` IDENTIFIED BY \'meh\' WITH MAX_USER_CONNECTIONS 20',
      ],
      [
        'foo',
        'bar',
        'meh',
        "MAX_USER_CONNECTIONS 20\nMAX_QUERIES_PER_HOUR 10",
        "GRANT ALL PRIVILEGES ON `foo`.* TO `bar`@`%` IDENTIFIED BY 'meh' WITH MAX_USER_CONNECTIONS 20\nMAX_QUERIES_PER_HOUR 10",
      ],
    ];
  }

}
