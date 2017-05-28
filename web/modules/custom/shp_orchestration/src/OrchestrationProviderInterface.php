<?php

namespace Drupal\shp_orchestration;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Interface OrchestrationProviderInterface.
 *
 * @package Drupal\shp_orchestration
 */
interface OrchestrationProviderInterface extends PluginInspectionInterface {

  /**
   * Returns the client
   *
   * @return mixed
   */
  public function getClient();

  /**
   * Creates a secret.
   *
   * @param string $name
   * @param array $data
   * @return mixed
   */
  public function createSecret($name, array $data);

  /**
   * Updates an existing secret.
   *
   * @param string $name
   * @param array $data
   * @return mixed
   */
  public function updateSecret($name, array $data);

  /**
   * Returns an existing secret
   *
   * @param string $name
   * @return mixed
   */
  public function getSecret($name);

  /**
   * Deletes an existing secret.
   *
   * @param string $name
   * @return mixed
   */
  public function deleteSecret($name);

  /**
   * Creates a distribution.
   *
   * @param string $name Name of the distribution.
   * @param array $data
   * @return mixed
   */
  public function createDistribution($name, array $data);

  /**
   * Updates an existing distribution.
   *
   * @param string $name Name of distribution.
   * @param array $data
   * @return mixed
   */
  public function updateDistribution($name, array $data);

  /**
   * Retrieves an existing distribution.
   *
   * @param string $name Name of distribution.
   * @return mixed
   */
  public function getDistribution($name);

  /**
   * Deletes an existing distribution.
   *
   * @param string $name Name of distribution.
   * @return mixed
   */
  public function deleteDistribution($name);

  /**
   * @param string $name Name of environment
   * @param array $data
   *
   * @return mixed
   */
  public function createEnvironment($name, array $data);

  /**
   * @return mixed
   */
  public function updateEnvironment();

  /**
   * @return mixed
   */
  public function getEnvironment();

  /**
   * @return mixed
   */
  public function deleteEnvironment();

  /**
   * @return mixed
   */
  public function createSite();

  /**
   * @return mixed
   */
  public function updateSite();

  /**
   * @return mixed
   */
  public function getSite();

  /**
   * @return mixed
   */
  public function deleteSite();
}
