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
   * Get/build the namespace from the sites shortname.
   *
   * @param int $site_id
   *   The site which dictates which service account quota will be used.
   */
  private function getSiteNamespace(int $site_id) {
    // Get the namespace associated with the site.
    $site = \Drupal::service('entity_type.manager')
      ->getStorage('node')
      ->load($site_id);
    $shortName = $site->field_shp_short_name->value;

    return $this->buildProjectName($shortName);
  }

  /**
   * Very simple helper so constructing the project name is in one place.
   *
   * @todo make configurable through the UI?
   *
   * @param string $shortName
   *   The short name of the project.
   *
   * @return string
   *   The 'shepherdified name'.
   */
  private function buildProjectName($shortName) {
    return 'shp-' . $shortName;
  }

}
