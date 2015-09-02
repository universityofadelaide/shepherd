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
   * @param \Drupal\Core\Entity\EntityInterface $site_instance
   *   The site_instance entity to apply job to.
   *
   * @return ResponseInterface
   *   The response from Jenkins.
   */
  public function job($job_type, $site_instance) {
    $server = reset($site_instance->field_ua_sm_server->referencedEntities());
    $environment = reset($site_instance->field_ua_sm_environment->referencedEntities());
    $site = reset($environment->field_ua_sm_site->referencedEntities());

    $query = [
      'job' => $this->config[$job_type . '_job'],
      'token' => $this->config['token'],
      'server_hostname' => $server->field_ua_sm_hostname->value,
      'site_instance_id' => $site_instance->id(),
      'site_id' => $site->id(),
      'site_uuid' => $site->uuid(),
    ];

    // Looks like there are some auth issues with anon read access.
    // buildByToken solves this issue.
    // todo: Ensure that the base url has a trailing slash.
    return $this->get('buildWithParameters', ['query' => $query]);
  }

}
