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
   * Creates artifacts in orchestration provider based on Shepherd distribution.
   *
   * @param string $name
   *   Name of the distribution.
   * @param string $builder_image
   *   An s2i-enabled image to use to build (and run) the source code.
   * @param string $source_repo
   *   Source code git repository.
   * @param string $source_ref
   *   Source code git ref, defaults to 'master'.
   * @param string|null $source_secret
   *   The secret to use when pulling and building the source git repository.
   *
   * @return bool
   *   Returns true if succeeded.
   */
  public function createdDistribution(
    string $name,
    string $builder_image,
    string $source_repo,
    string $source_ref = 'master',
    string $source_secret = NULL
  );

  /**
   * Updates artifacts in orchestration provider based on Shepherd distribution.
   *
   * @param string $name
   *   Name of the distribution.
   * @param string $builder_image
   *   An s2i-enabled image to use to build (and run) the source code.
   * @param string $source_repo
   *   Source code git repository.
   * @param string $source_ref
   *   Source code git ref, defaults to 'master'.
   * @param string|null $source_secret
   *   The secret to use when pulling and building the source git repository.
   *
   * @return bool
   *   Returns true if succeeded.
   */
  public function updatedDistribution(
    string $name,
    string $builder_image,
    string $source_repo,
    string $source_ref = 'master',
    string $source_secret = NULL
  );

  /**
   * Deletes an existing distribution.
   *
   * @param string $name Name of distribution.
   * @return mixed
   */
  public function deletedDistribution($name);

  /**
   * @param string $distribution_name
   *   Name of the distribution.
   * @param string $site_name
   *   Name of the site.
   * @param string $environment_name
   *   Name of the environment.
   * @param string $environment_id
   *   Unique id of the environment.
   * @param string $builder_image
   *   An s2i-enabled image to use to build (and run) the source code.
   * @param string $source_repo
   *   Source code git repository.
   * @param string $source_ref
   *   Source code git ref, defaults to 'master'.
   * @param string|null $source_secret
   *   The secret to use when pulling and building the source git repository.
   *
   * @return bool
   *   Returns true if succeeded.
   */
  public function createdEnvironment(
    string $distribution_name,
    string $site_name,
    string $environment_name,
    string $environment_id,
    string $builder_image,
    string $source_repo,
    string $source_ref = 'master',
    string $source_secret = NULL
  );

  /**
   * @return mixed
   */
  public function updatedEnvironment();

  /**
   * @return mixed
   */
  public function deletedEnvironment();

  /**
   * @return mixed
   */
  public function createdSite();

  /**
   * @return mixed
   */
  public function updatedSite();

  /**
   * @return mixed
   */
  public function deletedSite();

  /**
   * Retrieves the metadata on a stored secret.
   *
   * @param string $name
   *    Secret name.
   *
   * @return mixed
   *   Returns the secret metadata if successful.
   */
  public function getSecret(string $name);
}
