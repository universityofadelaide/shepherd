<?php

namespace Drupal\shp_backup\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\node\NodeInterface;
use Drupal\shp_orchestration\OrchestrationProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for listing backups.
 */
class BackupList extends ControllerBase {

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
   * BackupList constructor.
   *
   * @param \Drupal\shp_orchestration\OrchestrationProviderInterface $orchestrationProvider
   *   The orchestration provider plugin.
   * @param \Drupal\Core\Entity\EntityStorageInterface $nodeStorage
   *   The node storage.
   * @param \Drupal\Core\Datetime\DateFormatter $dateFormatter
   *   The date formatter.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   */
  public function __construct(OrchestrationProviderInterface $orchestrationProvider, EntityStorageInterface $nodeStorage, DateFormatter $dateFormatter, TimeInterface $time) {
    $this->orchestrationProvider = $orchestrationProvider;
    $this->nodeStorage = $nodeStorage;
    $this->dateFormatter = $dateFormatter;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.orchestration_provider')->getProviderInstance(),
      $container->get('entity_type.manager')->getStorage('node'),
      $container->get('date.formatter'),
      $container->get('datetime.time')
    );
  }

  /**
   * Builds backup list page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Current request.
   * @param \Drupal\node\NodeInterface $node
   *   The site node.
   *
   * @return array
   *   Render array
   */
  public function list(Request $request, NodeInterface $node) {
    /** @var \UniversityOfAdelaide\OpenShift\Objects\Backups\BackupList $backup_list */
    $backup_list = $this->orchestrationProvider->getBackupsForSite($node->id());
    $rows = [];
    foreach ($backup_list->getBackups() as $backup) {
      $environment = $this->nodeStorage->load($backup->getLabel('environment_id'));
      $rows[] = [
        $backup->getName(),
        $environment->toLink(),
        $backup->getPhase(),
        // These values aren't available until the backup has finished.
        $backup->isCompleted() ? $this->formatDate($backup->getStartTimestamp()) : $this->t('N/A'),
        $backup->isCompleted() ? $this->formatDate($backup->getCompletionTimestamp()) : $this->t('N/A'),
        $this->dateFormatter->formatInterval($this->parseDate($backup->getExpires())->getTimestamp() - $this->time->getRequestTime())
      ];
    }
    return [
      '#theme' => 'table',
      '#header' => [
        $this->t('Name'),
        $this->t('Environment'),
        $this->t('Phase'),
        $this->t('Started'),
        $this->t('Completed'),
        $this->t('Expires'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No backups for this site yet.'),
    ];
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
