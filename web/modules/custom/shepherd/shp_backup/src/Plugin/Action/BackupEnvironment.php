<?php

namespace Drupal\shp_backup\Plugin\Action;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Action\Plugin\Action\EntityActionBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\shp_backup\Service\Backup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Makes a node sticky.
 *
 * @Action(
 *   id = "shp_backup_environment",
 *   label = @Translation("Backup environment"),
 *   type = "node"
 * )
 */
class BackupEnvironment extends EntityActionBase {

  /**
   * Used to start backups.
   *
   * @var \Drupal\shp_backup\Service\Backup
   */
  protected $backupService;

  /**
   * Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a BackupEnvironment object.
   *
   * @param mixed[] $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\shp_backup\Service\Backup $backup
   *   Backup service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   Date formatter service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, Backup $backup, TimeInterface $time, DateFormatterInterface $date_formatter) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);
    $this->backupService = $backup;
    $this->time = $time;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('shp_backup.backup'),
      $container->get('datetime.time'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $object->access('update', $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    // Safety net against non environment nodes.
    if (!$object || $object->bundle() !== 'shp_environment') {
      $this->messenger()->addError('Backups can only be created against Environment nodes.');
      return;
    }
    if (!$site = $object->field_shp_site->entity) {
      $this->messenger()->addError('No site found for @name.', ['@name' => $object->label()]);
      return;
    }
    $backup_name = $this->t('Bulk backup - @date', [
      '@date' => $this->dateFormatter->format($this->time->getRequestTime(), 'medium'),
    ]);
    $this->backupService->createBackup($site, $object, $backup_name);
  }

}
