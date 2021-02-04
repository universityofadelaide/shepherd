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
    $client = new OpenShiftClient(
      $settings->get('connection.endpoint'),
      $settings->get('connection.token'),
      $settings->get('connection.namespace'),
      $settings->get('connection.verify_tls')
    );
    return $client;
  }

}
