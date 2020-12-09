<?php

namespace Drupal\Tests\shp_content_types\Functional;

use Drupal\Tests\shepherd\Functional\FunctionalTestBase;

/**
 * Tests environment constraints.
 *
 * @group shp_content_types
 */
class EnvironmentConstraintsTest extends FunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('shp_cache_backend_test'), 'Please enable the shp_cache_backend_test module.');
  }

  /**
   * Tests the replicas constraint.
   */
  public function testReplicasConstraint() {
    // Login to avoid moderation_state violations.
    $this->drupalLogin($this->createAdmin());
    $env = $this->createEnvironment([
      'field_min_replicas' => 4,
      'field_max_replicas' => 2,
    ]);
    $violations = $env->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals('Min replicas (<em class="placeholder">4</em>) must be less than Max replicas (<em class="placeholder">2</em>)', (string) $violations[0]->getMessage());
    $env->field_min_replicas->value = 1;
    $violations = $env->validate();
    $this->assertCount(0, $violations);
  }

}
