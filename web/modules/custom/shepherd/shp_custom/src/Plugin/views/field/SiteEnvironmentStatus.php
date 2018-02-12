<?php

namespace Drupal\shp_custom\Plugin\views\field;

use Drupal\shp_orchestration\Service\Status;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Field handler to add the status of the environment as a whole.
 *
 * This works by querying site instance(s) state.
 *
 * @package Drupal\shp_custom\Plugin\views\field
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("site_environment_status")
 */
class SiteEnvironmentStatus extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function query() {}

  /**
   * Shepherd orchestration status service.
   *
   * @var \Drupal\shp_orchestration\Service\Status
   *   Shepherd Orchestration status service.
   */
  protected $shpOrchestrationStatus;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Status $shp_orchestration_status) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->shpOrchestrationStatus = $shp_orchestration_status;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('shp_orchestration.status')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    // How do we get the status.
    // Use the orchestration provider to get the plugin.
    $environments_status = $this->shpOrchestrationStatus->get($values->_entity);

    // Is the environment running ?
    if ($environments_status['running']) {
      // If pods are available its running else its building.
      $status = ($environments_status['available_pods'] > 0) ? 'Running' : 'Building';
    }
    else {
      // If pods attempting to run but status is false, the state is broken.
      $status = ($environments_status['available_pods'] === 0) ? 'Stopped' : 'Failed';
    }

    $build['environment_status'] = [
      '#plain_text' => $status,
      // @todo - figure out cache tags for views field plugins.
      '#cache' => [
        'disabled' => TRUE,
      ],
    ];
    return $build;
  }

}
