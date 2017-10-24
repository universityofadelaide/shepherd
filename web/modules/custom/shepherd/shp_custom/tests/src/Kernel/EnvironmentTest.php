<?php

namespace Drupal\Tests\shp_custom\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

/**
 * Tests for the Environment Service.
 *
 * @group shp
 * @group shp_custom
 * @coversDefaultClass \Drupal\shp_custom\Service\Environment
 */
class EnvironmentTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'user',
    'node',
    'views',
    'field',
    'field_group',
    'shp_content_types',
    'shp_custom',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // @todo Fix missing $table parameter.
    $this->installSchema(['node', 'field', 'field_group', 'shp_content_types', 'shp_custom']);

    $user = $this->createUser();
    $container = \Drupal::getContainer();
    $container->get('current_user')->setAccount($user);

    $domain_name = '192.168.99.100.nip.io';

    $dev_env = Term::create([
      'vid' => 'shp_environment_types',
      'name' => 'Development',
      'field_shp_base_domain' => $domain_name,
    ]);
    $dev_env->save();

    $prd_env = Term::create([
      'vid' => 'shp_environment_types',
      'name' => 'Development',
    ]);
    $prd_env->save();

    $project = Node::create([
      'type'                     => 'shp_project',
      'langcode'                 => 'en',
      'uid'                      => '1',
      'status'                   => 1,
      'title'                    => 'WCMS D8',
      'field_shp_git_repository' => [['value' => 'git@gitlab.adelaide.edu.au:web-team/ua-wcms-d8.git']],
      'field_shp_builder_image'  => [['value' => 'uofa/s2i-shepherd-drupal']],
      'field_shp_build_secret'   => [['value' => 'build-key']],
    ]);

    $project->save();

    $site = Node::create([
      'type'                   => 'shp_site',
      'langcode'               => 'en',
      'uid'                    => '1',
      'status'                 => 1,
      'title'                  => 'Test Site',
      'field_shp_namespace'    => 'myproject',
      'field_shp_short_name'   => 'test',
      'field_shp_domain'       => $domain_name,
      'field_shp_path'         => '/',
      'field_shp_project' => [['target_id' => $project->id()]],
    ]);
    $site->moderation_state->value = 'published';
    $site->save();

  }

  public function testCreateEnvironment() {

    $environment = Node::create([
      'type'                       => 'shp_environment',
      'langcode'                   => 'en',
      'uid'                        => '1',
      'status'                     => 1,
      'field_shp_domain'           => '',
      'field_shp_path'             => '',
      'field_shp_environment_type' => [['target_id' => '']],
      'field_shp_git_reference'    => 'shepherd',
      'field_shp_site'             => '',
    ]);
    $environment->moderation_state->value = 'published';
    $environment->save();
  }

}
