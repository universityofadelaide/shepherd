<?php

namespace Drupal\shp_stats\Controller;

use Drupal\charts\Services\ChartsSettingsService;
use Drupal\Core\Controller\ControllerBase;
use Drupal\shp_stats\Service\Storage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SitesStatsController
 */
class SitesStatsController extends ControllerBase {

  /**
   * Storage service to retrieve stats from database.
   *
   * @var \Drupal\shp_stats\Service\Storage
   */
  protected $storageService;

  /**
   * Chart settings.
   *
   * @var \Drupal\charts\Services\ChartsSettingsService
   */
  protected $chartSettings;

  /**
   * SitesStatsController constructor.
   */
  public function __construct(Storage $storage, ChartsSettingsService $chartSettings) {
    $this->storageService = $storage;
    $this->chartSettings = $chartSettings;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('shp_stats.storage'),
      $container->get('charts.settings')
    );
  }

  /**
   * Render a list of entries in the database.
   */
  public function display() {

    $library = $this->chartSettings['library'];
    $options = [];
    $options['type'] = $this->chartSettings['type'];
    $options['title'] = $this->t('Chart title');
    $options['yaxis_title'] = $this->t('Y-Axis');
    $options['yaxis_min'] = '';
    $options['yaxis_max'] = '';
    $options['xaxis_title'] = $this->t('X-Axis');
    //sample data format
    $categories = ["Category 1", "Category 2", "Category 3", "Category 4"];
    $seriesData = [
      ["name" => "Series 1", "color" => "#0d233a", "type" => null, "data" => [250, 350, 400, 200]],
    ];

    $element = [
      '#theme' => 'shp_stats_site_stats',
      '#library' => $this->t($library),
      '#categories' => $categories,
      '#seriesData' => $seriesData,
      '#options' => $options,
    ];
    return $element;

    /*$entries = $this->storageService->load();
    $rows = [];
    foreach ($entries as $entry) {
      $rows[] = array_map('Drupal\Component\Utility\SafeMarkup::checkPlain', (array) $entry);
    }
    $markup['table'] = [
      '#theme' => 'charts',
      '#rows' => $rows,
      '#empty' => 'No data available',
    ];

    return $markup;*/
  }

}
