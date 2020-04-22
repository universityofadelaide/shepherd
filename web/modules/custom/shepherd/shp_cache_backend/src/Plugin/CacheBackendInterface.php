<?php

namespace Drupal\shp_cache_backend\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\node\NodeInterface;

/**
 * Defines an interface for CacheBackend plugins.
 */
interface CacheBackendInterface extends PluginInspectionInterface {

  /**
   * Gets environment variables to apply to an environment for a cache backend.
   *
   * @param \Drupal\node\NodeInterface $environment
   *   The environment node.
   *
   * @return array
   *   The array of environment variables.
   */
  public function getEnvironmentVariables(NodeInterface $environment);

  /**
   * Execute actions on environment creation.
   *
   * @param \Drupal\node\NodeInterface $environment
   *   The environment node.
   *
   * @throws \UniversityOfAdelaide\OpenShift\ClientException
   */
  public function onEnvironmentCreate(NodeInterface $environment);

  /**
   * Execute actions on environment deletion.
   *
   * @param \Drupal\node\NodeInterface $environment
   *   The environment node.
   *
   * @throws \UniversityOfAdelaide\OpenShift\ClientException
   */
  public function onEnvironmentDelete(NodeInterface $environment);

  /**
   * Execute actions on environment promotion.
   *
   * @param \Drupal\node\NodeInterface $environment
   *   The environment node.
   */
  public function onEnvironmentPromote(NodeInterface $environment);

  /**
   * Execute actions on environment demotion.
   *
   * @param \Drupal\node\NodeInterface $environment
   *   The environment node.
   */
  public function onEnvironmentDemotion(NodeInterface $environment);

}
