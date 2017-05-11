<?php

namespace Drupal\shp_custom\Service;

use Drupal\node\Entity\Node;

/**
 * Provides hosts configuration for export.
 *
 * @package Drupal\shp_custom
 */
class HostsConfig {

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
   *
   * @todo Shepherd: What does this have to do with generating config for export?
   *   It's about generating a UAT domain name for example.
   */
  public function generateDomainForEnv($site, $environment_type) {
    // Generate default domain.
    $environment_domains = \Drupal::config('shp_custom.settings')
      ->get('environment_domains');

    // Use site domain for production environments.
    if ($environment_type === 'prd') {
      return $site->field_shp_domain_name->value;
    }

    // For other environments, prepend the first token of the site domain.
    list($subdomain) = explode('.', $site->field_shp_domain_name->value);
    if (strlen($subdomain) && array_key_exists($environment_type, $environment_domains)) {
      $defaultDomain = $subdomain . '.' . $environment_domains[$environment_type];
    }
    else {
      // Fallback case.
      $defaultDomain = $site->field_shp_domain_name->value;
    }

    // Fetch site's existing domains.
    $environments = \Drupal::service('shp_custom.site')
      ->loadRelatedEntitiesByField($site, 'field_shp_site', 'shp_environment');
    $domains = array_map(function ($env) {
      return $env->field_shp_domain_name->value;
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
