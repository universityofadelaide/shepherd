<?php

/**
 * @file
 * Contains Drupal\ua_sm_custom\Service\JenkinsClient.
 */

namespace Drupal\ua_sm_custom\Service;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

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
   * @param int $site_instance_id
   *   The node ID of the site instance to use when running the job.
   * @param int $site_nid
   *   The node ID of the site to use when updating the build status of the job.
   * @param int $site_uuid
   *   The UUID of the site to use when updating the build status of the job.
   *
   * @return ResponseInterface
   *   The response from Jenkins.
   */
  public function job($job_type, $site_instance_id, $site_nid, $site_uuid) {
    $query = [
      'job' => $this->config[$job_type . '_job'],
      'token' => $this->config['token'],
      'site_instance_id' => $site_instance_id,
      'site_id' => $site_nid,
      'site_uuid' => $site_uuid,
    ];

    // Looks like there are some auth issues with anon read access.
    // buildByToken solves this issue.
    // todo: Ensure that the base url has a trailing slash.
    return $this->get('buildWithParameters', ['query' => $query]);
  }

}
