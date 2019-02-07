<?php

namespace Drupal\shp_reports\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\shp_custom\Service\Environment;
use Drupal\shp_orchestration\OrchestrationProviderPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for aggregator module routes.
 */
class EnvironmentVersionReportController extends ControllerBase {

  /**
   * Orchestration provider.
   *
   * @var \Drupal\shp_orchestration\OrchestrationProviderInterface
   */
  protected $orchestrationProvider;

  /**
   * Environment service.
   *
   * @var \Drupal\shp_custom\Service\Environment
   */
  protected $environment;

  /**
   * Constructs a \Drupal\aggregator\Controller\AggregatorController object.
   *
   * @param \Drupal\shp_orchestration\OrchestrationProviderInterface $orchestration_provider_plugin_manager
   *   Orchestration provider plugin.
   * @param \Drupal\shp_custom\Service\Environment $environment
   *   Environment service.
   */
  public function __construct(OrchestrationProviderPluginManagerInterface $orchestration_provider_plugin_manager, Environment $environment) {
    $this->orchestrationProvider = $orchestration_provider_plugin_manager->getProviderInstance();
    $this->environment = $environment;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.orchestration_provider'),
      $container->get('shp_custom.environment')
    );
  }

  /**
   * Displays the aggregator administration page.
   *
   * @return array
   *   A render array as expected by
   *   \Drupal\Core\Render\RendererInterface::render().
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function index() {
    $environments = $this->orchestrationProvider->getEnvironmentVersions();
    $header = [
      $this->t('Site'),
      $this->t('Environment'),
      $this->t('Version'),
      $this->t('Operations'),
    ];

    $rows = [];
    foreach ($environments as $env_id => $version) {
      if (!($env_node = $this->entityTypeManager()->getStorage('node')->load($env_id)) ||
        !($env_type = $this->environment->getEnvironmentType($env_node)) ||
        !($site = $this->environment->getSite($env_node))) {
        continue;
      }
      $row = [];
      $row[] = new Link($site->getTitle(), Url::fromRoute('view.shp_site_environments.page_1', ['node' => $site->id()]));
      $row[] = $env_type->getName();
      $row[] = $version;
      $links = [];
      $links['edit'] = [
        'title' => $this->t('Edit'),
        'url' => $env_node->toLink($this->t('Edit'), 'edit-form')->getUrl(),
      ];
      $row[] = [
        'data' => [
          '#type' => 'operations',
          '#links' => $links,
        ],
      ];
      $rows[] = $row;
    }

    $build = [];
    $build['environments'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No environments exist.'),
    ];

    return $build;
  }

}
