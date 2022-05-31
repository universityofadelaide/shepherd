<?php

namespace Drupal\shp_orchestration;

use Drupal\node\Entity\Node;

/**
 * Trait to provide a way to easily build token and namespace values.
 */
trait TokenNamespaceTrait {

  /**
   * Set the site token.
   *
   * @param int $site_id
   *   The site which dictates which service account quota will be used.
   *
   * @throws \UniversityOfAdelaide\OpenShift\ClientException
   */
  private function setSiteToken(int $site_id) {
    // Retrieve the service account associated with this site.
    $serviceAccount = \Drupal::service('shp_service_accounts')->getServiceAccount($site_id);
    $token = $serviceAccount->get('token');

    // Use lower level functions so we don't need site_id.
    $secret = $this->client->getSecret($token);
    return base64_decode($secret['data']['token']);
  }

  /**
   * Set the site namespace.
   *
   * @param int $site_id
   *   The site which dictates which service account quota will be used.
   */
  private function setSiteNamespace(int $site_id) {
    // Set the namespace associated with the site.
    $site = Node::load($site_id);
    $short_name = $site->field_shp_short_name->value;

    return 'shp-' . $short_name;
  }

}
