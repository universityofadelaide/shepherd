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
    return new static(
      $container->get('plugin.manager.orchestration_provider')
    );
  }

  /**
   * Retrieves site environments status via orchestrationProvider.
   *
   * @todo This api response function isn't actually finished.
   *
   * 1. It will get the memcache/redis instance as well as the drupal node.
   * 2. The $response array is built without any way of identifying either.
   * 3. The built response was erased each loop.
   * 4. It appears to have been created with the response from
   *    getDeploymentConfigs, but then partially changed.
   * 5. see extractDeploymentConfigStatus() in the OpenShift provider.
   * 6. Leaving it returning nonsense (as before), but at least no errors.
   * 7. Checked on prod, and always returns empty array '[]' there.
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
    foreach ($status as $deploymentConfigs) {
      if ($deploymentConfigs['running'] === FALSE && $deploymentConfigs['available_pods'] === 0) {
        // @todo Determine why status is false. What information to display ?
        $response[]['status'] = "Building or Scaled Down";
      }
      elseif ($deploymentConfigs['running'] === TRUE && $deploymentConfigs['available_pods'] >= 1) {
        $response[]['status'] = "Running";
      }
      else {
        // @todo What states end up here ?
        // Give a developer friendly message.
        $response[]['status'] = 'Unknown status';
      }
    }

    return new JsonResponse($response);
  }

}
