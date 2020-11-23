<?php

namespace Drupal\Tests\shepherd\Traits;

use Drupal\node\Entity\Node;

/**
 * Provides functions for creating content during functional tests.
 */
trait ContentCreationTrait {

  /**
   * Create a project node and mark it for cleanup.
   *
   * @param array $values
   *   Optional key => values to assign to the node.
   *
   * @return \Drupal\node\Entity\Node
   *   A node.
   */
  protected function createProject(array $values = []) {
    $values = $values + [
      'type' => 'shp_project',
      'title' => $this->randomMachineName(),
      'field_shp_git_repository' => [['value' => 'https://github.com/universityofadelaide/shepherd-example-drupal.git']],
      'field_shp_builder_image'  => [['value' => 'uofa/s2i-shepherd-drupal']],
      'field_shp_build_secret'   => [['value' => 'build-key']],
    ];
    return $this->doCreateNode($values);
  }

  /**
   * Create a site node and mark it for cleanup.
   *
   * @param array $values
   *   Optional key => values to assign to the node.
   *
   * @return \Drupal\node\Entity\Node
   *   A node.
   */
  protected function createSite(array $values = []) {
    $values = $values + [
      'type' => 'shp_site',
      'title' => $this->randomMachineName(),
      'field_shp_short_name' => $this->randomMachineName(),
      'field_shp_namespace' => $this->randomMachineName(),
      'field_shp_domain' => 'test-live.' . strtolower($this->randomMachineName(16)) . '.lol/',
      'field_shp_git_default_ref' => 'master',
      'field_shp_path' => '/',
      'field_shp_project' => $this->createProject(),
    ];
    return $this->doCreateNode($values);
  }

  /**
   * Create an environment node and mark it for cleanup.
   *
   * @param array $values
   *   Optional key => values to assign to the node.
   *
   * @return \Drupal\node\Entity\Node
   *   A node.
   */
  protected function createEnvironment(array $values = []) {
    $site = $values['field_shp_site'] ?? $this->createSite();
    $values = $values + [
      'type' => 'shp_environment',
      'title' => $this->randomMachineName(),
      'field_shp_domain' => 'test-0.' . strtolower($this->randomMachineName(16)) . '.lol',
      'field_shp_path' => $site->field_shp_path->value,
      'field_shp_environment_type' => $this->createEnvType(),
      'field_shp_git_reference' => 'master',
      'field_shp_site' => $site,
      'field_shp_cron_suspended' => 1,
      'moderation_state' => 'published',
    ];
    return $this->doCreateNode($values);
  }

  /**
   * Create a node and mark it for cleanup.
   *
   * @param array $values
   *   Array of key => values to assign to the node.
   *
   * @return \Drupal\node\Entity\Node
   *   A node.
   */
  private function doCreateNode(array $values) {
    /** @var \Drupal\node\Entity\Node $node */
    $node = Node::create($values);
    $node->save();
    $this->markEntityForCleanup($node);
    return $node;
  }

}
