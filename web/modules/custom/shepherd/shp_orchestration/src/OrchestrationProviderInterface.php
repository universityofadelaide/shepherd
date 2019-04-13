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
   * @param array $environment_variables
   *   Environment variables.
   *
   * @return bool
   *   Returns true if succeeded.
   */
  public function createdProject(
    string $name,
    string $builder_image,
    string $source_repo,
    string $source_ref = 'master',
    string $source_secret = NULL,
    array $environment_variables = []
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
   * @param array $environment_variables
   *   Environment variables.
   *
   * @return bool
   *   Returns true if succeeded.
   */
  public function updatedProject(
    string $name,
    string $builder_image,
    string $source_repo,
    string $source_ref = 'master',
    string $source_secret = '',
    array $environment_variables = []
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
   * @param string $storage_class
   *   The storage class to use when provisioning the PVC.
   * @param bool $update_on_image_change
   *   Whether to automatically rollout update to this environment.
   * @param bool $cron_suspended
   *   Whether cron is enabled on this environment.
   * @param array $environment_variables
   *   An array of key => value environment variables to set.
   * @param array $secrets
   *   An array of secrets to attach to the deployment.
   * @param array $probes
   *   Details of the liveness/readiness probe to use for this deployment.
   * @param array $cron_jobs
   *   An array of cron jobs associated with this environment.
   * @param array $annotations
   *   An array of route annotations.
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
    string $storage_class = '',
    bool $update_on_image_change = FALSE,
    bool $cron_suspended = FALSE,
    array $environment_variables = [],
    array $secrets = [],
    array $probes = [],
    array $cron_jobs = [],
    array $annotations = []
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
   * @param string $storage_class
   *   The storage class to use when provisioning the PVC.
   * @param bool $update_on_image_change
   *   Whether to automatically rollout update to this environment.
   * @param bool $cron_suspended
   *   Whether cron is enabled on this environment.
   * @param array $environment_variables
   *   An array of key => value environment variables to set.
   * @param array $secrets
   *   An array of secrets to attach to the deployment.
   * @param array $probes
   *   Details of the liveness/readiness probe to use for this deployment.
   * @param array $cron_jobs
   *   An array of cron jobs associated with this environment.
   * @param array $annotations
   *   An array of route annotations.
   *
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
    string $domain,
    string $path,
    string $source_repo,
    string $source_ref = 'master',
    string $source_secret = NULL,
    string $storage_class = '',
    bool $update_on_image_change = FALSE,
    bool $cron_suspended = FALSE,
    array $environment_variables = [],
    array $secrets = [],
    array $probes = [],
    array $cron_jobs = [],
    array $annotations = []
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
   * Updates the environment in the selected orchestration provider.
   *
   * @param string $project_name
   *   Name of the project.
   * @param string $short_name
   *   Short name of the site.
   * @param int $site_id
   *   Unique id of the site.
   * @param int $environment_id
   *   Unique id of the environment.
   * @param string $domain_name
   *   The domain name of the site.
   * @param string $path
   *   The path of the site.
   * @param array $annotations
   *   An array of route annotations.
   * @param string $source_ref
   *   Source code git ref, defaults to 'master'.
   * @param bool $clear_cache
   *   Execute a cache clear job after promotion?
   *
   * @return bool
   *   Returns true if succeeded.
   */
  public function promotedEnvironment(
    string $project_name,
    string $short_name,
    int $site_id,
    int $environment_id,
    string $domain_name,
    string $path,
    array $annotations,
    string $source_ref = 'master',
    bool $clear_cache = TRUE
  );

  /**
   * Handles a site being created.
   *
   * @param string $project_name
   *   The project that is being deployed on the site.
   * @param string $short_name
   *   The short name of the site.
   * @param int $site_id
   *   The site id.
   * @param string $domain_name
   *   The domain name of the site.
   * @param string $path
   *   The path of the site.
   *
   * @return bool
   *   Returns true if succeeded.
   */
  public function createdSite(string $project_name, string $short_name, int $site_id, string $domain_name, string $path);

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
   * @param string $project_name
   *   The project that is being deployed on the site.
   * @param string $short_name
   *   The short name of the site.
   * @param int $site_id
   *   The site id.
   *
   * @return bool
   *   Returns true if succeeded.
   */
  public function deletedSite(string $project_name, string $short_name, int $site_id);

  /**
   * Retrieves the metadata on a stored secret.
   *
   * @param string $name
   *   Secret name.
   * @param string $key
   *   Optional key name to return.
   *
   * @return array|string|bool
   *   Returns the secret array if successful, the value of the key, or false.
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
   * Generates a deployment name from Shepherd node id.
   *
   * @param string $id
   *   Id of the name to be generated.
   *
   * @return string
   *   Returns the generated deployment name.
   */
  public static function generateDeploymentName(string $id);

  /**
   * Get the status of a collection of environments related to a site.
   *
   * @param string $site_id
   *   Unique id of the site, used a label for environments.
   *
   * @return array|bool
   *   Returns a collection of environments and their statuses,
   *   false if unsuccessful.
   */
  public function getSiteEnvironmentsStatus(string $site_id);

  /**
   * Get the status of a given environment.
   *
   * @param string $project_name
   *   Name of the project.
   * @param string $short_name
   *   Short name of the site.
   * @param string $environment_id
   *   Environment node id.
   *
   * @return array|bool
   *   Returns the given environment status, or false.
   */
  public function getEnvironmentStatus(
    string $project_name,
    string $short_name,
    string $environment_id
  );

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
   * Retrieves the direct terminal access url for a given environment.
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
  public function getTerminalUrl(
    string $project_name,
    string $short_name,
    string $environment_id
  );

  /**
   * Retrieves the direct log access url for a given environment.
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
  public function getLogUrl(
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

  /**
   * Get a job.
   *
   * @param string $name
   *   Name of the job to retrieve.
   */
  public function getJob(
    string $name
  );

}
