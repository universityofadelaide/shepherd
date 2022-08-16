<?php

namespace Drupal\shp_orchestration;

/**
 * Trait to provide a way to easily build token and namespace values.
 *
 * Means we need to use service methods and not injection ;-(
 */
trait TokenNamespaceTrait {

  /**
   * Get the token from the sites linked service account.
   *
   * @param int $site_id
   *   The site which dictates which service account quota will be used.
   *
   * @throws \UniversityOfAdelaide\OpenShift\ClientException
   */
  private function getSiteToken(int $site_id) {
    // Retrieve the service account associated with this site.
    return \Drupal::service('shp_service_accounts')
      ->getServiceAccount($site_id)
      ->get('token');
  }

  /**
   * Very simple helper so constructing the project name is in one place.
   *
   * @param int $site_id
   *   The short name of the project.
   *
   * @return string
   *   The 'shepherdified name'.
   */
  private function buildProjectName(int $site_id): string {
    $prefix = $this->config->get('connection.site_deploy_prefix') ?? 'shepherd-dev';
    return $prefix . $site_id;
  }

}
