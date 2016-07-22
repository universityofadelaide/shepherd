<?php

namespace Drupal\ua_sm_custom\Service;

use Drupal\Core\Entity\EntityInterface;
use GuzzleHttp\Client;
use Drupal\Core\Url;

/**
 * Provides a client for triggering jobs in Jenkins.
 *
 * @package Drupal\ua_sm_custom
 */
class JenkinsClient extends Client {

  /**
   * The jenkins configuration object.
   *
   * @var array
   */
  private $config;

  /**
   * Supported Jenkins jobs.
   */
  const BACKUP_JOB = 'backup_job';
  const CLONE_JOB = 'clone_job';
  const DECOMMISSION_JOB = 'decommission_job';
  const DEPLOY_JOB = 'deploy_job';
  const RESTORE_JOB = 'restore_job';

  /**
   * Override constructor and feed in base URI from config.
   */
  public function __construct() {
    $this->config = \Drupal::config('ua_sm_custom.settings')->get('jenkins');
    parent::__construct(['base_uri' => $this->config['base_uri']]);
  }

  /**
   * Runs a given jenkins job.
   *
   * @param string $job_type
   *   The type of jenkins job to run.
   * @param \Drupal\Core\Entity\EntityInterface $site_instance
   *   The site_instance entity to apply job to.
   *
   * @return ResponseInterface
   *   The response from Jenkins.
   */
  public function job($job_type, EntityInterface $site_instance) {
    $environments = $site_instance->field_ua_sm_environment->referencedEntities();
    $environment = reset($environments);
    $platforms = $environment->field_ua_sm_platform->referencedEntities();
    $platform = reset($platforms);
    $build_servers = $platform->field_ua_sm_build_server->referencedEntities();
    $build_server = reset($build_servers);
    $deploy_servers = $site_instance->field_ua_sm_server->referencedEntities();
    $deploy_server = reset($deploy_servers);

    $build_host_ssh = $build_server->field_ua_sm_ssh_user->value . '@' . $build_server->field_ua_sm_hostname->value;
    $deploy_host_ssh = $deploy_server->field_ua_sm_ssh_user->value . '@' . $deploy_server->field_ua_sm_hostname->value;

    $uasm_site_url = trim(Url::fromUri('base:', ['absolute' => TRUE])->toString(), '/');

    // Create token with environment_id and unix timestamp of backup to restore.
    if (isset($site_instance->backup_timestamp) && isset($site_instance->backup_env_id)) {
      $backup_token = $site_instance->backup_env_id . '/' . $site_instance->backup_timestamp;
    }
    else {
      $backup_token = NULL;
    }

    $query = [
      'job' => $this->config[$job_type],
      'token' => $this->config['token'],
      'BUILD_HOST_SSH' => $build_host_ssh,
      'DEPLOY_HOST_SSH' => $deploy_host_ssh,
      'SITE_INSTANCE_ID' => $site_instance->id(),
      'SM_SITE_URL' => $uasm_site_url,
      'BACKUP_TOKEN' => $backup_token,
    ];

    // Looks like there are some auth issues with anon read access.
    // buildByToken solves this issue.
    // todo: Ensure that the base url has a trailing slash.
    return $this->get('buildWithParameters', ['query' => $query]);
  }

}
