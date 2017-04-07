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
   * Retrieves a secret that matches the name/tag.
   *
   * @param string $name
   *   Name of secret to be retrieved.
   *
   * @return mixed
   *   Returns the secret, base64 decoded.
   */
  public function getSecret($name);

  /**
   * Creates a new secret using the name provided.
   *
   * @param string $name
   *   Name of the secret to be stored.
   * @param array $data
   *   Array of key => values to be stored. These will base64 encoded.
   *
   * @return mixed
   *   Returns response from orchestration api.
   */
  public function createSecret($name, array $data);

  /**
   * Updates an existing secret using the name provided.
   *
   * @param string $name
   *   Name of the secret to be updated.
   * @param array $data
   *   Array of key => values to be stored. These will be base64 encoded.
   *
   * @return mixed
   *   Returns response from orchestration api.
   */
  public function updateSecret($name, array $data);

  /**
   * Retrieves a service that matches the name.
   *
   * @param string $name
   *   Name of the service to retrieved.
   *
   * @return mixed
   *   Returns the response from the orchestration api.
   */
  public function getService($name);

  /**
   * Creates a new service based on the name and config data given.
   *
   * @param string $name
   *    Name of service.
   * @param array $data
   *    Configuration data for service.
   *
   * @return mixed
   *    Returns the response from the orchestration api.
   */
  public function createService($name, array $data);

  /**
   * Retrieves a pod collection according to name.
   *
   * @param string $name
   *   The name of the collection to retrieve.
   *
   * @return mixed
   *    Returns the response form the orchestration api.
   */
  public function getPods($name);

  /**
   * Creates a new pod based on the name and config provided.
   *
   * @param string $name
   *   Name of the pod to be created.
   * @param array $config
   *   A key value array of config data needed to create a pod.
   *
   * @return mixed
   *   Returns the response from the orchestration api.
   */
  public function createPod($name, array $config);
}
