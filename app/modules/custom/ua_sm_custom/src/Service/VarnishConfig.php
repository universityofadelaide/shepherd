<?php

namespace Drupal\ua_sm_custom\Service;

/**
 * Provides varnish configuration for export.
 *
 * @package Drupal\ua_sm_custom
 */
class VarnishConfig {

  /**
   * Generates a varnish configuration.
   *
   * @return array
   *   The varnish configuration.
   */
  public function generate() {
    $varnish_config = [];

    $query = \Drupal::entityQuery('node')
      ->condition('type', 'ua_sm_site_instance');
    $instance_ids = $query->execute();

    foreach ($instance_ids as $nid) {
      $site_instance = \Drupal::entityManager()->getStorage('node')->load($nid);

      $environments = $site_instance->field_ua_sm_environment->referencedEntities();
      $environment = reset($environments);
      $status = $environment->field_ua_sm_environment_status->value;

      if (!$status) {
        continue;
      }

      $servers = $site_instance->field_ua_sm_server->referencedEntities();
      $server = reset($servers);
      $sites = $environment->field_ua_sm_site->referencedEntities();
      $site = reset($sites);

      if (!isset($varnish_config[$environment->id()])) {
        $varnish_config[$environment->id()] = [
          'domain' => $environment->field_ua_sm_domain_name->value,
          'id' => $environment->id(),
          'path' => $site->field_ua_sm_path->value,
          'environment' => $environment->field_ua_sm_machine_name->value,
          'servers' => [],
        ];
      }

      $varnish_config[$environment->id()]['servers'][] = [
        'host' => $server->field_ua_sm_hostname->value,
        'port' => $site_instance->field_ua_sm_http_port->value,
      ];
    }

    return array_values($varnish_config);
  }

}
