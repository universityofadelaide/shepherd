<?php

namespace Drupal\shp_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Provides JSON responses for site environment(s).
 *
 * @package Drupal\shp_orchestration\Controller
 */
class SiteEnvironmentsController extends ControllerBase {

  /**
   * Selected orchestration provider manager.
   *
   * @var \Drupal\shp_orchestration\OrchestrationProviderInterface
   */
  protected $orchestrationProvider;

  /**
   * Constructs a OrchestrationProviderSettingsController.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $orchestration_provider_manager
   *   Orchestration Provider Manager.
   */
  public function __construct(PluginManagerInterface $orchestration_provider_manager) {

    $this->orchestrationProvider = $orchestration_provider_manager->getProviderInstance();

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static (
      $container->get('plugin.manager.orchestration_provider')
    );
  }

  /**
   * Retrieves site environments status via orchestrationProvider.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The site node entity.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Json object with the status data.
   */
  public function getStatus(NodeInterface $node) {
    $site_id = $node->id();
    $status = $this->orchestrationProvider->getSiteEnvironmentsStatus($site_id);
    $response = [];
    foreach ($status['items'] as $deploymentConfigs) {
      $deployment_status = $deploymentConfigs['status']['conditions'][0];
      $response = [];
      if (strtolower($deployment_status['status']) === 'false' && $deployment_status['type'] === "Available") {
        // @todo - Determine why status is false. What information to display ?
        $response[]['status'] = "Building";
      }
      elseif (strtolower($deployment_status['status']) === 'true' && $deployment_status['type'] === "Available") {
        $response[]['status'] = "Running";
      }
      else {
        // @todo - What states end up here ?
        // Give a developer friendly message.
        $response[]['status'] = "Status : " . $deployment_status['message'];
      }
    }

    return new JsonResponse($response);
  }

}
