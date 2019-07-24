<?php

namespace Drupal\shp_backup\Controller;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\shp_backup\Service\Backup;
use Drupal\shp_custom\Service\Environment;
use Drupal\shp_orchestration\OrchestrationProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base controller for lists of backups/restores.
 */
abstract class ListControllerBase extends ControllerBase {

  /**
   * The orchestration provider plugin.
   *
   * @var \Drupal\shp_orchestration\OrchestrationProviderInterface
   */
  protected $orchestrationProvider;

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The backup service.
   *
   * @var \Drupal\shp_backup\Service\Backup
   */
  protected $backupService;

  /**
   * The environment service.
   *
   * @var \Drupal\shp_custom\Service\Environment
   */
  protected $environmentService;

  /**
   * ListControllerBase constructor.
   *
   * @param \Drupal\shp_orchestration\OrchestrationProviderInterface $orchestrationProvider
   *   The orchestration provider plugin.
   * @param \Drupal\Core\Entity\EntityStorageInterface $nodeStorage
   *   The node storage.
   * @param \Drupal\Core\Datetime\DateFormatter $dateFormatter
   *   The date formatter.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   * @param \Drupal\shp_backup\Service\Backup $backupService
   *   The backup service.
   * @param \Drupal\shp_custom\Service\Environment $environmentService
   *   The environment service.
   */
  public function __construct(OrchestrationProviderInterface $orchestrationProvider, EntityStorageInterface $nodeStorage, DateFormatter $dateFormatter, TimeInterface $time, Backup $backupService, Environment $environmentService) {
    $this->orchestrationProvider = $orchestrationProvider;
    $this->nodeStorage = $nodeStorage;
    $this->dateFormatter = $dateFormatter;
    $this->time = $time;
    $this->backupService = $backupService;
    $this->environmentService = $environmentService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.orchestration_provider')->getProviderInstance(),
      $container->get('entity_type.manager')->getStorage('node'),
      $container->get('date.formatter'),
      $container->get('datetime.time'),
      $container->get('shp_backup.backup'),
      $container->get('shp_custom.environment')
    );
  }

  /**
   * Formats the ISO 8601 date string into a readable date.
   *
   * @param string $date
   *   The date string.
   *
   * @return string
   *   The return date string.
   */
  protected function formatDate($date): string {
    $date = $this->parseDate($date);
    return $date->format('d/m/Y H:i');
  }

  /**
   * Parses a date from a backup.
   *
   * @param string $date
   *   The date string.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   */
  protected function parseDate($date): DrupalDateTime {
    $date = DrupalDateTime::createFromFormat('Y-m-d\TH:i:s\Z', $date, 'Etc/Zulu');
    $date->setTimezone(new \DateTimeZone(drupal_get_user_timezone()));
    return $date;
  }

}
