<?php

namespace Drupal\shp_orchestration;

use Drupal\Core\Config\ConfigFactoryInterface;
use UniversityOfAdelaide\OpenShift\Client as OpenShiftClient;

/**
 * Factory for creating OpenshiftClients.
 */
class OpenShiftClientFactory {

  /**
   * OpenShiftClientFactory constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;
  }

  /**
   * Constructs an openshift client.
   *
   * @return \UniversityOfAdelaide\OpenShift\Client
   *   The client.
   */
  public function getClient() {
    $settings = $this->configFactory->get('shp_orchestration.settings');
    try {
      $serviceAccount = \Drupal::service('shp_service_accounts.random')
        ->getServiceAccount();
      $token = $serviceAccount->get('token');
    }
    catch (\Exception $e) {
      $token = $settings->get('connection.token');
    }

    $client = new OpenShiftClient(
      $settings->get('connection.endpoint'),
      $token,
      $settings->get('connection.namespace'),
      $settings->get('connection.verify_tls')
    );
    return $client;
  }

}
