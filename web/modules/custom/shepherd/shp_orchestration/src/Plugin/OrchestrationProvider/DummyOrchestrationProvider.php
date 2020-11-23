<?php

namespace Drupal\shp_orchestration\Plugin\OrchestrationProvider;

use Drupal\shp_orchestration\OrchestrationProviderBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UniversityOfAdelaide\OpenShift\Objects\Backups\Backup;
use UniversityOfAdelaide\OpenShift\Objects\Hpa;

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
    array $annotations = [],
    string $backup_schedule = '',
    int $backup_retention = 0
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
    array $annotations = [],
    string $backup_schedule = '',
    int $backup_retention = 0,
  ) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function deletedEnvironment(
    string $project_name,
    string $short_name,
    string $environment_id
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
    string $domain,
    string $path,
    array $annotations,
    string $source_ref = 'master',
    bool $clear_cache = TRUE,
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
  public function deletedSite(
    string $project_name,
    string $short_name,
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
  public function getBackupsForSite(string $site_id) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getBackupsForEnvironment(string $environment_id) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function restoreEnvironment(string $backup_name, string $site_id, string $environment_id) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRestoresForSite(string $site_id) {
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
  public function getSecret(string $name, string $key = NULL) {
    return $key ? 'secret' : ['secret'];
  }

  /**
   * {@inheritdoc}
   */
  public function createSecret(string $name, array $data) {
    return ['secret'];
  }

  /**
   * {@inheritdoc}
   */
  public function updateSecret(string $name, array $data) {
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
  public function getEnvironmentUrl(string $project_name, string $short_name, string $environment_id) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getTerminalUrl(string $project_name, string $short_name, string $environment_id) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getLogUrl(string $project_name, string $short_name, string $environment_id) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironmentStatus(string $project_name, string $short_name, string $environment_id) {
    return FALSE;
  }

}
