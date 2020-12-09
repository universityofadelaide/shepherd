<?php

namespace Drupal\Tests\shepherd\Functional;

use Drupal\FunctionalTests\AssertLegacyTrait;
use Drupal\Tests\PhpunitCompatibilityTrait;
use Drupal\Tests\RandomGeneratorTrait;
use Drupal\Tests\UiHelperTrait;
use Drupal\Tests\shepherd\Traits\ContentCreationTrait;
use Drupal\Tests\shepherd\Traits\TaxonomyCreationTrait as UATaxonomyCreationTrait;
use Drupal\Tests\shepherd\Traits\UserCreationTrait as UAUserCreationTrait;
use PHPUnit\Framework\TestCase;
use weitzman\DrupalTestTraits\DrupalTrait;
use weitzman\DrupalTestTraits\Entity\NodeCreationTrait;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;
use weitzman\DrupalTestTraits\Entity\UserCreationTrait;
use weitzman\DrupalTestTraits\GoutteTrait;

/**
 * A base class for testing an installed UoA site.
 */
class FunctionalTestBase extends TestCase {

  use AssertLegacyTrait;
  use DrupalTrait;
  use GoutteTrait;
  use PhpunitCompatibilityTrait;
  use UiHelperTrait;
  use NodeCreationTrait {
    getNodeByTitle as drupalGetNodeByTitle;
    createNode as drupalCreateNode;
  }
  use RandomGeneratorTrait;
  use UserCreationTrait {
    createRole as drupalCreateRole;
    createUser as drupalCreateUser;
  }
  use TaxonomyCreationTrait;

  use ContentCreationTrait;
  use UATaxonomyCreationTrait;
  use UAUserCreationTrait;

  /**
   * {@inheritdoc}
   *
   * Browser tests are run in separate processes to prevent collisions between
   * code that may be loaded by tests.
   */
  protected $runTestInSeparateProcess = TRUE;

  /**
   * The database prefix of this test run.
   *
   * @var string
   */
  protected $databasePrefix;

  /**
   * Config to reset.
   *
   * @var array
   */
  protected $resetConfig = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->setupMinkSession();
    $this->setupDrupal();
    // Ensure we use the dummy OS provider.
    $this->setConfigValues([
      'shp_orchestration.settings' => [
        'selected_provider' => 'dummy_orchestration_provider',
      ],
      'shp_database_provisioner.settings' => [
        'enabled' => 0,
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    parent::tearDown();
    $this->setConfigValues($this->resetConfig, FALSE);
    $this->tearDownDrupal();
    $this->tearDownMinkSession();
  }

  /**
   * Override \Drupal\Tests\UiHelperTrait::prepareRequest().
   *
   * Since it generatesan error, and does nothing useful for DTT.
   *
   * @see https://www.drupal.org/node/2246725
   */
  protected function prepareRequest() {
    // No-op.
  }

  /**
   * Configuration accessor for tests. Returns non-overridden configuration.
   *
   * @param string $name
   *   Configuration name.
   *
   * @return \Drupal\Core\Config\Config
   *   The configuration object with original configuration data.
   */
  protected function config($name) {
    return $this->container->get('config.factory')->getEditable($name);
  }

  /**
   * Set config values noting future values if required.
   *
   * @param array $config_values
   *   Array of array values keyed by config object key.
   * @param bool $mark
   *   TRUE to record value for resetting later.
   */
  protected function setConfigValues(array $config_values, $mark = TRUE) {
    $config = $this->container->get('config.factory');
    foreach ($config_values as $key => $values) {
      /** @var \Drupal\Core\Config\ImmutableConfig $entry */
      $entry = $config->getEditable($key);
      if ($mark && !isset($this->resetConfig[$key])) {
        $this->resetConfig[$key] = [];
      }
      foreach ($values as $value_key => $value) {
        if ($mark) {
          $this->resetConfig[$key][$value_key] = $entry->get($value_key);
        }
        $entry->set($value_key, $value);
      }
      $entry->save();
    }
  }

  /**
   * Load the last created entity of a given type.
   *
   * @param string $type
   *   The type of entity to load.
   * @param int $offset
   *   The offset of the entity to grab.
   * @param bool $mark_for_cleanup
   *   Optionally mark the entity for cleanup.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   A loaded entity.
   */
  protected function loadLastCreatedEntity($type, $offset = 0, $mark_for_cleanup = FALSE) {
    $type_manager = \Drupal::entityTypeManager();
    $id_key = $type_manager->getDefinition($type)->getKey('id');
    $results = \Drupal::entityQuery($type)->sort($id_key, 'DESC')->range($offset, $offset + 1)->execute();
    $id = array_shift($results);
    $entity = $type_manager->getStorage($type)->load($id);
    if ($mark_for_cleanup) {
      $this->markEntityForCleanup($entity);
    }
    return $entity;
  }

}
