<?php

namespace Drupal\shp_orchestration\Plugin\OrchestrationProvider;

use Drupal\shp_orchestration\OrchestrationProviderBase;

/**
 * DummyOrchestrationProvider.
 *
 * @OrchestrationProvider(
 *   id = "dummy_orchestration_provider",
 *   name = "Dummy",
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
  public function createdProject(string $name, string $builder_image, string $source_repo, string $source_ref = 'master', string $source_secret = NULL) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function updatedProject(string $name, string $builder_image, string $source_repo, string $source_ref = 'master', string $source_secret = NULL) {
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
    array $environment_variables = [],
    array $secrets = [],
    array $cron_jobs = []
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
    array $cron_jobs = []
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
  public function createdSite() {
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
  public function deletedSite() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function backupEnvironment(
    string $project_name,
    string $short_name,
    string $environment_id,
    string $source_ref = 'master',
    string $commands = ''
  ) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function restoreEnvironment(
    string $project_name,
    string $short_name,
    string $environment_id,
    string $source_ref = 'master',
    string $commands = ''
  ) {
    return [];
  }

  /**
   * {@inheritdoc}
   *
   * @todo - can this and cron job creation be combined?
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
  public static function generateDeploymentName(
    string $project_name,
    string $short_name,
    string $environment_id
  ) {
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

}
