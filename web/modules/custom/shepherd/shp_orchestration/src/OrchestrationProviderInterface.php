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
   * Creates artifacts in orchestration provider based on Shepherd project.
   *
   * @param string $name
   *   Name of the project.
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
  public function createdProject(
    string $name,
    string $builder_image,
    string $source_repo,
    string $source_ref = 'master',
    string $source_secret = NULL
  );

  /**
   * Updates artifacts in orchestration provider based on Shepherd project.
   *
   * @param string $name
   *   Name of the project.
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
  public function updatedProject(
    string $name,
    string $builder_image,
    string $source_repo,
    string $source_ref = 'master',
    string $source_secret = NULL
  );

  /**
   * Deletes an existing project.
   *
   * @param string $name
   *   Name of project.
   *
   * @return bool
   *   Returns true if succeeded.
   */
  public function deletedProject($name);

  /**
   * Creates an environment in the selected orchestration provider.
   *
   * @param string $project_name
   *   Name of the project.
   * @param string $short_name
   *   Short name of the site.
   * @param string $site_id
   *   Unique id of the site.
   * @param string $environment_id
   *   Unique id of the environment.
   * @param string $environment_url
   *   Absolute url for the environment.
   * @param string $builder_image
   *   An s2i-enabled image to use to build (and run) the source code.
   * @param string $domain
   *   The domain associated with the environment.
   * @param string $path
   *   The path associated with the environment.
   * @param string $source_repo
   *   Source code git repository.
   * @param string $source_ref
   *   Source code git ref, defaults to 'master'.
   * @param string|null $source_secret
   *   The secret to use when pulling and building the source git repository.
   * @param array $environment_variables
   *   An array of key => value environment variables to set.
   * @param array $secrets
   *   An array of secrets to attach to the deployment.
   * @param array $cron_jobs
   *   An array of cron jobs associated with this environment.
   *
   * @return bool
   *   Returns true if succeeded.
   */
  public function createdEnvironment(
    string $project_name,
    string $short_name,
    string $site_id,
    string $environment_id,
    string $environment_url,
    string $builder_image,
    string $domain,
    string $path,
    string $source_repo,
    string $source_ref = 'master',
    string $source_secret = NULL,
    array $environment_variables = [],
    array $secrets = [],
    array $cron_jobs = []
  );

  /**
   * Updates the environment in the selected orchestration provider.
   *
   * @param string $project_name
   *   Name of the project.
   * @param string $short_name
   *   Short name of the site.
   * @param string $site_id
   *   Unique id of the site.
   * @param string $environment_id
   *   Unique id of the environment.
   * @param string $environment_url
   *   Absolute url for the environment.
   * @param string $builder_image
   *   An s2i-enabled image to use to build (and run) the source code.
   * @param string $source_repo
   *   Source code git repository.
   * @param string $source_ref
   *   Source code git ref, defaults to 'master'.
   * @param string|null $source_secret
   *   The secret to use when pulling and building the source git repository.
   * @param array $cron_jobs
   *   An array of cron jobs associated with this environment.

   * @return bool
   *   Returns true if succeeded.
   */
  public function updatedEnvironment(
    string $project_name,
    string $short_name,
    string $site_id,
    string $environment_id,
    string $environment_url,
    string $builder_image,
    string $source_repo,
    string $source_ref = 'master',
    string $source_secret = NULL,
    array $cron_jobs = []
  );

  /**
   * Delete the environment in the orchestration provider.
   *
   * @todo Shepherd: refactor to be like the archive below, or remove?
   *
   * @param string $project_name
   *   Name of the project.
   * @param string $short_name
   *   Short name of the site.
   * @param string $environment_id
   *   Unique id of the environment.
   *
   * @return bool
   *   Returns true if succeeded.
   */
  public function deletedEnvironment(
    string $project_name,
    string $short_name,
    string $environment_id
  );

  /**
   * Archive the environment in the orchestration provider.
   *
   * @param int $environment_id
   *   Unique id of the environment.
   *
   * @return bool
   *   Returns true if succeeded.
   */
  public function archivedEnvironment(
    int $environment_id
  );

  /**
   * Handles a site being created.
   *
   * @return bool
   *   Returns true if succeeded.
   */
  public function createdSite();

  /**
   * Handles a site being updated.
   *
   * @return bool
   *   Returns true if succeeded.
   */
  public function updatedSite();

  /**
   * Handles a site being deleted.
   *
   * @return bool
   *   Returns true if succeeded.
   */
  public function deletedSite();

  /**
   * Retrieves the metadata on a stored secret.
   *
   * @param string $name
   *   Secret name.
   * @param string $key
   *   Optional key name to return.
   *
   * @return array|string|bool
   *   Returns the secret array if successful, the value of the key if set, or
   *   false.
   */
  public function getSecret(string $name, string $key = NULL);

  /**
   * Creates a secret.
   *
   * @param string $name
   *   The name of the secret to be stored.
   * @param array $data
   *   Key value array of secret data.
   *
   * @return array|bool
   *   Returns the secret array if successful, otherwise false.
   */
  public function createSecret(string $name, array $data);

  /**
   * Updates a secret.
   *
   * @param string $name
   *   The name of the secret to be updated.
   * @param array $data
   *   Key value array of secret data.
   *
   * @return array|bool
   *   Returns the secret metadata if successful.
   */
  public function updateSecret(string $name, array $data);

  /**
   * Generates a deployment name from Shepherd entities.
   *
   * @param string $project_name
   *   Name of the project.
   * @param string $short_name
   *   Short name of the site.
   * @param string $environment_id
   *   Environment node id.
   *
   * @return string
   *   Returns the generated deployment name.
   */
  public static function generateDeploymentName(
    string $project_name,
    string $short_name,
    string $environment_id
  );

  /**
   * Get the status of a collection of environments related to a site.
   *
   * @param string $site_id
   *   Unique id of the site, used a label for environments.
   *
   * @return string|bool
   *   Returns a collection of environments and their statuses,
   *   false if unsuccessful.
   */
  public function getSiteEnvironmentsStatus(string $site_id);

  /**
   * Retrieves the url for a given environment.
   *
   * @param string $project_name
   *   Name of the project.
   * @param string $short_name
   *   Short name of the site.
   * @param string $environment_id
   *   Environment node id.
   *
   * @return \Drupal\Core\Url|bool
   *   Returns environment url, or false.
   */
  public function getEnvironmentUrl(
    string $project_name,
    string $short_name,
    string $environment_id
  );

  /**
   * Backup an environment.
   *
   * @param string $project_name
   *   Name of the project.
   * @param string $short_name
   *   Short name of the site.
   * @param string $environment_id
   *   Environment node id.
   * @param string $source_ref
   *   Source code git ref, defaults to 'master'.
   * @param string $commands
   *   Commands to run to perform the backup.
   *
   * @return array|bool
   *   Returns a response body if successful, otherwise false.
   */
  public function backupEnvironment(
    string $project_name,
    string $short_name,
    string $environment_id,
    string $source_ref = 'master',
    string $commands = ''
  );

  /**
   * Restore an environment.
   *
   * @param string $project_name
   *   Name of the project.
   * @param string $short_name
   *   Short name of the site.
   * @param string $environment_id
   *   Environment node id.
   * @param string $source_ref
   *   Source code git ref, defaults to 'master'.
   * @param string $commands
   *   Commands to run to perform the backup.
   *
   * @return array|bool
   *   Returns a response body if successful, otherwise false.
   */
  public function restoreEnvironment(
    string $project_name,
    string $short_name,
    string $environment_id,
    string $source_ref = 'master',
    string $commands = ''
  );

  /**
   * Execute a job.
   *
   * @param string $project_name
   *   Name of the project.
   * @param string $short_name
   *   Short name of the site.
   * @param string $environment_id
   *   Environment node id.
   * @param string $source_ref
   *   Source code git ref, defaults to 'master'.
   * @param string $commands
   *   Commands to run to perform the backup.
   *
   * @return array|bool
   *   Returns a response body if successful, otherwise false.
   */
  public function executeJob(
    string $project_name,
    string $short_name,
    string $environment_id,
    string $source_ref = 'master',
    string $commands = ''
  );

}
