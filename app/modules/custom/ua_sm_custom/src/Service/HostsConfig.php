<?php

namespace Drupal\ua_sm_custom\Service;

use Drupal\node\Entity\Node;

/**
 * Provides hosts configuration for export.
 *
 * @package Drupal\ua_sm_custom
 */
class HostsConfig {

  /**
   * Generates a hosts configuration.
   *
   * @return array
   *   The hosts configuration.
   */
  public function generate() {
    $hosts_config = [];

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

      if (!isset($hosts_config[$environment->id()])) {
        $hosts_config[$environment->id()] = [
          'domain' => $environment->field_ua_sm_domain_name->value,
          'id' => $environment->id(),
          'path' => $site->field_ua_sm_path->value,
          'environment' => $environment->field_ua_sm_machine_name->value,
          'servers' => [],
        ];
      }

      $hosts_config[$environment->id()]['servers'][] = [
        'host' => $server->field_ua_sm_hostname->value,
        'port' => $site_instance->field_ua_sm_http_port->value,
      ];
    }

    return array_values($hosts_config);
  }

  /**
   * Generates a domain name for an environment based on the following rules.
   *
   * 1. If the environment type is 'prd' then the site's domain is used.
   * 2. Otherwise, the first token from the site's domain will be prepended to
   *    the domain that corresponds to the environment type. For example, a STG
   *    environment for a site with the domain winecentre.com.au will be given
   *    the domain winecentre.stg.adelaide.edu.au.
   * 3. If anything is missing or broken, the site's domain is used.
   *
   * Caveat: this function does not care about uniqueness of domain/path
   * combinations.
   *
   * @param Node $site
   *   The parent site node.
   * @param string $environment_type
   *   The environment type machine name, i.e. stg, dev, prd.
   *
   * @return string
   *   The resultant generated domain name.
   */
  public function generateDomainForEnv($site, $environment_type) {
    // Generate default domain.
    $environment_domains = \Drupal::config('ua_sm_custom.settings')
      ->get('environment_domains');

    // Use site domain for production environments.
    if ($environment_type === 'prd') {
      return $site->field_ua_sm_domain_name->value;
    }

    // For other environments, prepend the first token of the site domain.
    list($subdomain) = explode('.', $site->field_ua_sm_domain_name->value);
    if (strlen($subdomain) && array_key_exists($environment_type, $environment_domains)) {
      $defaultDomain = $subdomain . '.' . $environment_domains[$environment_type];
    }
    else {
      // Fallback case.
      $defaultDomain = $site->field_ua_sm_domain_name->value;
    }

    // Fetch site's existing domains.
    $environments = \Drupal::service('ua_sm_custom.site')
      ->loadRelatedEntitiesByField($site, 'field_ua_sm_site', 'ua_sm_environment');
    $domains = array_map(function ($env) {
      return $env->field_ua_sm_domain_name->value;
    }, $environments);

    $domain = $defaultDomain;
    $i = 1;

    // Generate a unique domain.
    while (in_array($domain, $domains)) {
      $pieces = explode('.', $defaultDomain);
      $pieces[0] .= '-' . $i;
      $domain = implode('.', $pieces);
      $i++;
    }

    return $domain;
  }

}
