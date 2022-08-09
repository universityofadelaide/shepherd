<?php

namespace Drupal\shp_orchestration;

use Drupal\Component\Plugin\PluginInspectionInterface;
use UniversityOfAdelaide\OpenShift\Objects\Backups\Backup;
use UniversityOfAdelaide\OpenShift\Objects\Hpa;
use UniversityOfAdelaide\OpenShift\Objects\Route;

/**
 * Defines an interface for orchestration providers.
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
   * @param int $site_id
   *   Unique id of the site.
   * @param int $environment_id
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
   * @param string $storage_class
   *   The storage class to use when provisioning the PVC.
   * @param int $storage_size
   *   The amount of storage to claim with the PVC.
   * @param int $backup_size
   *   The amount of backup storage to claim with the PVC.
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
   * @param string $backup_schedule
   *   A schedule to run automated backups on, leave blank to disable.
   * @param int $backup_retention
   *   The number of scheduled backups to retain.
   * @param \UniversityOfAdelaide\OpenShift\Objects\Route|null $route
   *   A Route to create, or NULL if one shouldn't be created.
   *
   * @return bool
   *   Returns true if succeeded.
   */
  public function createdEnvironment(
    string $project_name,
    string $short_name,
    int $site_id,
    int $environment_id,
    string $environment_url,
    string $builder_image,
    string $source_repo,
    string $source_ref = 'master',
    string $source_secret = NULL,
    string $storage_class = '',
    int $storage_size = 3,
    int $backup_size = 3,
    bool $update_on_image_change = FALSE,
    bool $cron_suspended = FALSE,
    array $environment_variables = [],
    array $secrets = [],
    array $probes = [],
    array $cron_jobs = [],
    string $backup_schedule = '',
    int $backup_retention = 0,
    Route $route = NULL
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
   * @param string $storage_class
   *   The storage class to use when provisioning the PVC.
   * @param int $storage_size
   *   The amount of storage to claim with the PVC.
   * @param int $backup_size
   *   The amount of backup storage to claim with the PVC.
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
   * @param string $backup_schedule
   *   A schedule to run automated backups on, leave blank to disable.
   * @param int $backup_retention
   *   The number of scheduled backups to retain.
   * @param \UniversityOfAdelaide\OpenShift\Objects\Route|null $route
   *   A Route to create, or NULL if one shouldn't be created.
   * @param \UniversityOfAdelaide\OpenShift\Objects\Hpa|null $hpa
   *   An HPA to create, or NULL if one shouldn't be created.
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
    string $source_repo,
    string $source_ref = 'master',
    string $source_secret = NULL,
    string $storage_class = '',
    int $storage_size = 3,
    int $backup_size = 3,
    bool $update_on_image_change = FALSE,
    bool $cron_suspended = FALSE,
    array $environment_variables = [],
    array $secrets = [],
    array $probes = [],
    array $cron_jobs = [],
    string $backup_schedule = '',
    int $backup_retention = 0,
    Route $route = NULL,
    Hpa $hpa = NULL
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
   * @param int $site_id
   *   Unique id of the site.
   * @param int $environment_id
   *   Unique id of the environment.
   *
   * @return bool
   *   Returns true if succeeded.
   */
  public function deletedEnvironment(
    string $project_name,
    string $short_name,
    int $site_id,
    int $environment_id
  );

  /**
   * Archive the environment in the orchestration provider.
   *
   * Unused, see nodeUpdate() in NodeOperations.php in shp_custom.
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
   * @param string $source_ref
   *   Source code git ref, defaults to 'master'.
   * @param bool $clear_cache
   *   Execute a cache clear job after promotion?
   * @param \UniversityOfAdelaide\OpenShift\Objects\Route|null $route
   *   A Route to create, or NULL if one shouldn't be created.
   * @param \UniversityOfAdelaide\OpenShift\Objects\Hpa|null $hpa
   *   An HPA to create, or NULL if one shouldn't be created.
   *
   * @return bool
   *   Returns true if succeeded.
   */
  public function promotedEnvironment(
    string $project_name,
    string $short_name,
    int $site_id,
    int $environment_id,
    string $source_ref = 'master',
    bool $clear_cache = TRUE,
    Route $route = NULL,
    Hpa $hpa = NULL
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
   * Has to be done before the actual site node is deleted to have the
   * required information to delete the associated objects.
   *
   * @param string $project_name
   *   The project that is being deployed on the site.
   * @param int $site_id
   *   The site id.
   *
   * @return bool
   *   Returns true if succeeded.
   */
  public function preDeleteSite(string $project_name, int $site_id);

  /**
   * Retrieves the metadata on a stored secret.
   *
   * @param int $site_id
   *   The site id.
   * @param string $name
   *   Secret name.
   * @param string $key
   *   Optional key name to return.
   *
   * @return array|string|bool
   *   Returns the secret array if successful, the value of the key, or false.
   */
  public function getSecret(int $site_id, string $name, string $key = NULL);

  /**
   * Creates a secret.
   *
   * @param int $site_id
   *   The site id.
   * @param string $name
   *   The name of the secret to be stored.
   * @param array $data
   *   Key value array of secret data.
   *
   * @return array|bool
   *   Returns the secret array if successful, otherwise false.
   */
  public function createSecret(int $site_id, string $name, array $data);

  /**
   * Updates a secret.
   *
   * @param int $site_id
   *   The site id.
   * @param string $name
   *   The name of the secret to be updated.
   * @param array $data
   *   Key value array of secret data.
   *
   * @return array|bool
   *   Returns the secret metadata if successful.
   */
  public function updateSecret(int $site_id, string $name, array $data);

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
   * @param int $site_id
   *   Site id to observe.
   * @param int $environment_id
   *   Environment node id.
   *
   * @return array|bool
   *   Returns the given environment status, or false.
   */
  public function getEnvironmentStatus(
    int $site_id,
    int $environment_id
  );

  /**
   * Retrieves the url for a given environment.
   *
   * @param int $site_id
   *   Site id to observe.
   * @param int $environment_id
   *   Environment node id.
   *
   * @return \Drupal\Core\Url|bool
   *   Returns environment url, or false.
   */
  public function getEnvironmentUrl(
    int $site_id,
    int $environment_id
  );

  /**
   * Retrieves the direct terminal access url for a given environment.
   *
   * @param int $site_id
   *   Site id to observe.
   * @param int $environment_id
   *   Environment node id.
   *
   * @return \Drupal\Core\Url|bool
   *   Returns environment url, or false.
   */
  public function getTerminalUrl(
    int $site_id,
    int $environment_id
  );

  /**
   * Retrieves the direct log access url for a given environment.
   *
   * @param int $site_id
   *   Site id to observe.
   * @param int $environment_id
   *   Environment node id.
   *
   * @return \Drupal\Core\Url|bool
   *   Returns environment url, or false.
   */
  public function getLogUrl(
    int $site_id,
    int $environment_id
  );

  /**
   * Get a backup.
   *
   * @param string $name
   *   The backup name.
   *
   * @return \UniversityOfAdelaide\OpenShift\Objects\Backups\Backup|bool
   *   Returns a backup object if successful, otherwise false.
   */
  public function getBackup(string $name);

  /**
   * Update a backup.
   *
   * @param \UniversityOfAdelaide\OpenShift\Objects\Backups\Backup $backup
   *   The backup.
   *
   * @return \UniversityOfAdelaide\OpenShift\Objects\Backups\Backup|bool
   *   Returns a backup object if successful, otherwise false.
   */
  public function updateBackup(Backup $backup);

  /**
   * Delete a backup.
   *
   * @param string $name
   *   The backup name.
   *
   * @return bool
   *   Returns true if succeeded.
   */
  public function deleteBackup(string $name);

  /**
   * Backup an environment.
   *
   * @param string $site_id
   *   Site node id.
   * @param string $environment_id
   *   Environment node id.
   * @param string $friendly_name
   *   An optional friendly name for the backup.
   *
   * @return object|bool
   *   Returns a backup object if successful, otherwise false.
   */
  public function backupEnvironment(string $site_id, string $environment_id, string $friendly_name = '');

  /**
   * Schedules backups for an environment.
   *
   * @param string $site_id
   *   Site node id.
   * @param string $environment_id
   *   Environment node id.
   * @param string $schedule
   *   A cron expression defining when to run the backups.
   * @param int $retention
   *   The number of scheduled backups to retain.
   *
   * @return object|bool
   *   Returns a schedule object if successful, otherwise false.
   */
  public function environmentScheduleBackupCreate(string $site_id, string $environment_id, string $schedule, int $retention);

  /**
   * Updates the backup schedule for an environment.
   *
   * @param string $site_id
   *   Site node id.
   * @param string $environment_id
   *   Environment node id.
   * @param string $schedule
   *   A cron expression defining when to run the backups.
   * @param int $retention
   *   The number of scheduled backups to retain.
   *
   * @return object|bool
   *   Returns the schedule object if successful, otherwise false.
   */
  public function environmentScheduleBackupUpdate(string $site_id, string $environment_id, string $schedule, int $retention);

  /**
   * Deletes the backup schedule for an environment.
   *
   * @param string $environment_id
   *   Environment node id.
   *
   * @return bool
   *   Returns if it was successful or not.
   */
  public function environmentScheduleBackupDelete(string $environment_id);

  /**
   * Get a list of backups for a site.
   *
   * @param int $site_id
   *   The site node id.
   *
   * @return object|bool
   *   The list of backups.
   */
  public function getBackupsForSite(int $site_id);

  /**
   * Get a list of backups for an environment.
   *
   * @param int $site_id
   *   The site node id.
   * @param int $environment_id
   *   The environment node id.
   *
   * @return object|bool
   *   The list of backups.
   */
  public function getBackupsForEnvironment(int $site_id, int $environment_id);

  /**
   * Restore an environment.
   *
   * @param string $backup_name
   *   Name of the backup.
   * @param int $site_id
   *   Site node id.
   * @param int $environment_id
   *   Environment node id.
   *
   * @return array|bool
   *   Returns a response body if successful, otherwise false.
   */
  public function restoreEnvironment(string $backup_name, int $site_id, int $environment_id);

  /**
   * Get a list of restores for a site.
   *
   * @param int $site_id
   *   The site node id.
   *
   * @return object|bool
   *   The list of restores.
   */
  public function getRestoresForSite(int $site_id);

  /**
   * Backup an environment.
   *
   * @param int $site_id
   *   Site node id.
   * @param int $from_env
   *   Environment node id to backup.
   * @param int $to_env
   *   Environment node id to restore.
   *
   * @return object|bool
   *   Returns a sync object if successful, otherwise false.
   */
  public function syncEnvironments(int $site_id, int $from_env, int $to_env);

  /**
   * Get a list of all syncs.
   *
   * @param int $site_id
   *   The site node id.
   *
   * @return object|bool
   *   The list of syncs.
   */
  public function getSyncs(int $site_id);

  /**
   * Get a list of syncs for a site.
   *
   * @param int $site_id
   *   The site node id.
   *
   * @return object|bool
   *   The list of syncs.
   */
  public function getSyncsForSite(int $site_id);

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
