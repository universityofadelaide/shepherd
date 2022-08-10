<?php

namespace Drupal\shp_orchestration\Plugin\OrchestrationProvider;

use Drupal\shp_orchestration\OrchestrationProviderBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UniversityOfAdelaide\OpenShift\Objects\Backups\Backup;
use UniversityOfAdelaide\OpenShift\Objects\Hpa;
use UniversityOfAdelaide\OpenShift\Objects\Route;

/**
 * A mock orchestration provider.
 *
 * @OrchestrationProvider(
 *   id = "dummy_orchestration_provider",
 *   name = "None",
 *   description = @Translation("Dummy provider to disable orchestration tasks"),
 * )
 */
class DummyOrchestrationProvider extends OrchestrationProviderBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    // Don't bother calling parent constructor.
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function createdProject(string $name, string $builder_image, string $source_repo, string $source_ref = 'master', string $source_secret = NULL, array $environment_variables = []) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function updatedProject(string $name, string $builder_image, string $source_repo, string $source_ref = 'master', string $source_secret = '', array $environment_variables = []) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function deletedProject($name) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
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
  ) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
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
  ) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function deletedEnvironment(
    string $project_name,
    string $short_name,
    int $site_id,
    int $environment_id
  ) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function archivedEnvironment(
    int $environment_id
  ) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
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
  ) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function createdSite(
    string $project_name,
    string $short_name,
    int $site_id,
    string $domain,
    string $path
  ) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function updatedSite() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function preDeleteSite(
    string $project_name,
    int $site_id
  ) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getBackup(string $name) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function updateBackup(Backup $backup) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteBackup(string $name) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function backupEnvironment(string $site_id, string $environment_id, string $friendly_name = '') {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function environmentScheduleBackupCreate(string $site_id, string $environment_id, string $schedule, int $retention) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function environmentScheduleBackupUpdate(string $site_id, string $environment_id, string $schedule, int $retention) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function environmentScheduleBackupDelete(string $environment_id) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getBackupsForSite(int $site_id) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getBackupsForEnvironment(int $site_id, int $environment_id) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function restoreEnvironment(string $backup_name, int $site_id, int $environment_id) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRestoresForSite(int $site_id) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function syncEnvironments(int $site_id, int $from_env, int $to_env) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSyncs(int $site_id) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSyncsForSite(int $site_id) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function executeJob(
    string $project_name,
    string $short_name,
    string $environment_id,
    string $source_ref = 'master',
    string $commands = ''
  ) {
    return [];
  }

  /**
   * Fetch the job from the provider.
   *
   * @param string $name
   *   The job name.
   *
   * @return array|bool
   *   The job, else false.
   */
  public function getJob(string $name) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironmentVersions() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSecret(int $site_id, string $name, string $key = NULL) {
    return $key ? 'secret' : ['secret'];
  }

  /**
   * {@inheritdoc}
   */
  public function createSecret(int $site_id, string $name, array $data) {
    return ['secret'];
  }

  /**
   * {@inheritdoc}
   */
  public function updateSecret(int $site_id, string $name, array $data) {
    return ['secret'];
  }

  /**
   * {@inheritdoc}
   */
  public static function generateDeploymentName(string $id) {
    return 'deployment_name';
  }

  /**
   * {@inheritdoc}
   */
  public function getSiteEnvironmentsStatus(string $site_id) {
    return [
      'items' => [
        0 => [
          'status' => [
            'conditions' => [
              0 => [
                'message' => 'Orchestration disabled',
              ],
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironmentUrl(int $site_id, int $environment_id) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getTerminalUrl(int $site_id, int $environment_id) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getLogUrl(int $site_id, int $environment_id) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironmentStatus(int $site_id, int $environment_id) {
    return FALSE;
  }

}
