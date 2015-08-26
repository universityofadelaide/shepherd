<?php

/**
 * @file
 * Contains Drupal\ua_sm_custom\Service\PortManager.
 */

namespace Drupal\ua_sm_custom\Service;
use Drupal\node\Entity\Node;

/**
 * Provides management of a servers ports.
 *
 * @package Drupal\ua_sm_custom
 */
class PortManager {

  public $port_fields = [
    'field_ua_sm_ssh_port',
    'field_ua_sm_http_port',
  ];

  /**
   * @param $server_id
   * @return array
   */
  public function getUsedPorts($server_id) {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'ua_sm_site_instance')
      ->condition('field_ua_sm_server', $server_id)
      ->condition('field_ua_sm_state', 'stopped', '<>');

    $site_instances_ids = $query->execute();
    $site_instances = Node::loadMultiple($site_instances_ids);

    $used_port_numbers = [];
    foreach ($site_instances as $site_instance) {
      foreach ($this->port_fields as $port_field) {
        $used_port_numbers[] = $site_instance->{$port_field}->value;
      }
    }

    return $used_port_numbers;
  }

  /**
   * @param $server_id
   * @param $number_of_ports
   * @return array|bool
   */
  public function getAvailablePorts($server_id, $number_of_ports) {
    $used_port_numbers = $this->getUsedPorts($server_id);

    $server = Node::load($server_id);
    $port_range_start = $server->field_ua_sm_port_range_start->value;
    $port_range_end = $server->field_ua_sm_port_range_end->value;

    if (count($used_port_numbers) == $port_range_end - $port_range_start) {
      // @TODO: Handle case where there weren't enough free ports on server.
      return FALSE;
    }

    $available_port_numbers = [];
    for ($port_number = $port_range_start; $port_number <= $port_range_end; $port_number++) {
      if (!in_array($port_number, $used_port_numbers)) {
        $available_port_numbers[] = $port_number;
        if (count($available_port_numbers) == $number_of_ports) {
          break;
        }
      }
    }

    return $available_port_numbers;
  }

}
