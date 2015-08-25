<?php

/**
 * @file
 * Contains Drupal\ua_sm_custom\Service\SiteInstanceConfig.
 */

namespace Drupal\ua_sm_custom\Service;

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

      $config = [
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
        ],
        'drupal_config' => [
          'system.site' => [
            'name' => $site->field_ua_sm_site_title->value,
          ],
          'ua_footer.authorized' => [
            'name' => $site->field_ua_sm_authorizer_id->value,
            'email' => $site->field_ua_sm_authorizer_email->value,
          ],
          'system.ua_menu' => [
            'top_menu_style' => $site->field_ua_sm_top_menu_style->value,
          ],
        ],
        'drupal_settings' => [],
        'environment' => [
          'deployment_type' => $environment->field_ua_sm_deployment_type->value,
          'domain_name' => $environment->field_ua_sm_domain_name->value,
          'id' => $environment->id(),
          'git_reference' => $environment->field_ua_sm_git_reference->value,
          'machine_name' => $environment->field_ua_sm_machine_name->value,
        ],
        'platform' => [
          'build_server' => $platform->field_ua_sm_build_server->value,
          'docker_registry' => $platform->field_ua_sm_docker_registry->value,
          'id' => $platform->id(),
          'reverse_proxy_addresses' => $platform->field_ua_sm_rev_proxy_addresses->value,
          'reverse_proxy_header' => $platform->field_ua_sm_reverse_proxy_header->value,
          'task_runner' => $platform->field_ua_sm_task_runner->value,
        ],
        'server' => [
          'hostname' => $server->field_ua_sm_hostname->value,
          'id' => $server->id(),
          'ip_address' => $server->field_ua_sm_ip_address->value,
          'ipv6_address' => $server->field_ua_sm_ipv6_address->value,
          'port_range_start' => $server->field_ua_sm_port_range_start->value,
          'port_range_end' => $server->field_ua_sm_port_range_end->value,
        ],
        'site' => [
          'admin_email' => $site->field_ua_sm_admin_email->value,
          'admin_password' => $site->field_ua_sm_admin_password->value,
          'admin_user' => $site->field_ua_sm_admin_user->value,
          'aliases' => $site->field_ua_sm_aliases->value,
          'authorizer_email' => $site->field_ua_sm_authorizer_email->value,
          'authorizer_id' => $site->field_ua_sm_authorizer_id->value,
          'domain_name' => $site->field_ua_sm_domain_name->value,
          'id' => $site->id(),
          'path' => $site->field_ua_sm_path->value,
          'site_id' => $site->field_ua_sm_site_id->value,
          'site_title' => $site->field_ua_sm_site_title->value,
          'top_menu_style' => $site->field_ua_sm_top_menu_style->value,
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
        ],
      ];
      return $config;
    }
    return [];
  }

}
