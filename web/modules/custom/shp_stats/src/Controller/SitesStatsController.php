<?php

namespace Drupal\shp_stats\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\shp_stats\Service\Storage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SitesStatsController
 */
class SitesStatsController extends ControllerBase {

  /**
   * @var \Drupal\shp_stats\Service\Storage
   */
  protected $storageService;

  /**
   * SitesStatsController constructor.
   */
  public function __construct(Storage $storage) {
    $this->storageService = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('shp_stats.storage')
      $container->get('charts.settings')
    );
  }

  /**
   * Render a list of entries in the database.
   */
  public function display() {
    $entries = $this->storageService->load();
    $rows = [];
    foreach ($entries as $entry) {
      $rows[] = array_map('Drupal\Component\Utility\SafeMarkup::checkPlain', (array) $entry);
    }
    $markup['table'] = [
      '#type' => 'table',
      '#rows' => $rows,
      '#empty' => 'No data available',
    ];

    return $markup;
  }

}
