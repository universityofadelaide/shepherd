<?php

/**
 * @file
 * Contains Drupal\ua_sm_custom\Service\SiteInstanceConfig.
 */

namespace Drupal\ua_sm_custom\Service;

use Drupal\user\Entity\User;

/**
 * Provides site configuration for export.
 *
 * @package Drupal\ua_sm_custom
 */
class SiteInstanceConfig {

  /**
   * Generates a site configuration for a given site instance.
   *
   * @param int $nid
   *   The node ID of the site instance for which to generate config.
   *
   * @return array
   *   The site instance configuration.
   */
  public function generate($nid) {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'ua_sm_site_instance')
      ->condition('nid', $nid);
    $result = $query->execute();
    if ($result) {
      $site_instance = \Drupal::entityManager()->getStorage('node')->load($nid);
      $server = reset($site_instance->field_ua_sm_server->referencedEntities());
      $environment = reset($site_instance->field_ua_sm_environment->referencedEntities());
      $platform = reset($environment->field_ua_sm_platform->referencedEntities());
      $site = reset($environment->field_ua_sm_site->referencedEntities());
      $distribution = reset($site->field_ua_sm_distribution->referencedEntities());
      $build_server = reset($platform->field_ua_sm_build_server->referencedEntities());
      $database_servers = $platform->field_ua_sm_database_servers->referencedEntities();
      $web_servers = $platform->field_ua_sm_web_servers->referencedEntities();

      // @TODO: add users keys with 'develop' field_ua_sm_role.
      // $users = \Drupal::service('ua_sm_custom.site')->loadUsers($site, 'developer');

      $user_ids = \Drupal::entityQuery('user')
        ->condition('roles', 'administrator')
        ->execute();
      $users = User::loadMultiple($user_ids);

      $keys = \Drupal::service('ua_sm_custom.user')->loadKeys($users);

      $reverse_proxy_addresses = [];
      foreach ($platform->get('field_ua_sm_rev_proxy_addresses') as $address) {
        $reverse_proxy_addresses[] = reset($address->getValue());
      }

      $config = [
        'custom' => json_decode($environment->field_ua_sm_custom_config->value),
        'database' => [
          'database' => $environment->field_ua_sm_database->value,
          'driver' => $environment->field_ua_sm_database_driver->value,
          'host' => $environment->field_ua_sm_database_host->value,
          'password' => $environment->field_ua_sm_database_password->value,
          'port' => $environment->field_ua_sm_database_port->value,
          'username' => $environment->field_ua_sm_database_username->value,
        ],
        'distribution' => [
          'box_type' => $distribution->field_ua_sm_box_type->value,
          'git_repository' => $distribution->field_ua_sm_git_repository->value,
          'id' => $distribution->id(),
          'uuid' => $distribution->uuid(),
        ],
        'environment' => [
          'deployment_type' => $environment->field_ua_sm_deployment_type->value,
          'domain_name' => $environment->field_ua_sm_domain_name->value,
          'git_reference' => $environment->field_ua_sm_git_reference->value,
          'hash_salt' => $environment->field_ua_sm_hash_salt->value,
          'id' => $environment->id(),
          'machine_name' => $environment->field_ua_sm_machine_name->value,
          'uuid' => $environment->uuid(),
        ],
        'platform' => [
          'build_server' => $this->formatServer($build_server),
          'docker_registry' => $platform->field_ua_sm_docker_registry->value,
          'id' => $platform->id(),
          'reverse_proxy_addresses' => $reverse_proxy_addresses,
          'reverse_proxy_header' => $platform->field_ua_sm_reverse_proxy_header->value,
          'task_runner' => $platform->field_ua_sm_task_runner->value,
          'database_servers' => $this->formatServers($database_servers),
          'uuid' => $platform->uuid(),
          'web_servers' => $this->formatServers($web_servers),
        ],
        'server' => $this->formatServer($server),
        'site' => [
          'admin_email' => $site->field_ua_sm_admin_email->value,
          'admin_password' => $site->field_ua_sm_admin_password->value,
          'admin_user' => $site->field_ua_sm_admin_user->value,
          'aliases' => $site->field_ua_sm_aliases->value,
          'authoriser_email' => $site->field_ua_sm_authoriser_email->value,
          'authoriser_name' => $site->field_ua_sm_authoriser_name->value,
          'maintainer_email' => $site->field_ua_sm_maintainer_email->value,
          'maintainer_name' => $site->field_ua_sm_maintainer_name->value,
          'domain_name' => $site->field_ua_sm_domain_name->value,
          'id' => $site->id(),
          'keys' => $keys,
          'path' => $site->field_ua_sm_path->value,
          'site_title' => $site->field_ua_sm_site_title->value,
          'top_menu_style' => $site->field_ua_sm_top_menu_style->value,
          'uuid' => $site->uuid(),
        ],
        'site_instance' => [
          'git_reference' => $site_instance->field_ua_sm_git_reference->value,
          'hostname' => $site_instance->field_ua_sm_hostname->value,
          'http_port' => $site_instance->field_ua_sm_http_port->value,
          'id' => $site_instance->id(),
          'ip_address' => $site_instance->field_ua_sm_ip_address->value,
          'ipv6_address' => $site_instance->field_ua_sm_ipv6_address->value,
          'ssh_port' => $site_instance->field_ua_sm_ssh_port->value,
          'state' => $site_instance->field_ua_sm_state->value,
          'uuid' => $site_instance->uuid(),
        ],
        'site_manager' => [
          'hostname' => $_SERVER['HTTP_HOST'],
        ],
      ];

      // Set default database config if none specified.
      if (!$config['database']['database']) {
        $config['database']['database'] = $environment->id();
      }
      if (!$config['database']['username']) {
        $config['database']['username'] = $environment->id();
      }

      return $config;
    }
    return [];
  }

  /**
   * Format server nodes into a sensible structure.
   *
   * @param array $servers
   *   An array of loaded server nodes.
   *
   * @return array
   *   Formatted servers.
   */
  public function formatServers($servers) {
    $formatted_servers = [];
    foreach ($servers as $server) {
      $formatted_servers[] = $this->formatServer($server);
    }
    return $formatted_servers;
  }

  /**
   * Format a server node into a sensible structure.
   *
   * @param \Drupal\node\Entity\Node $server
   *   A loaded server node.
   *
   * @return array
   *   Formatted properties of a server.
   */
  public function formatServer($server) {
    return [
      'hostname' => $server->field_ua_sm_hostname->value,
      'id' => $server->id(),
      'ip_address' => $server->field_ua_sm_ip_address->value,
      'ipv6_address' => $server->field_ua_sm_ipv6_address->value,
      'port_range_start' => $server->field_ua_sm_port_range_start->value,
      'port_range_end' => $server->field_ua_sm_port_range_end->value,
      'uuid' => $server->uuid(),
    ];
  }

}
