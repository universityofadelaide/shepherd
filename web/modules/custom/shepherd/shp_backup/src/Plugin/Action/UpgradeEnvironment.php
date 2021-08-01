<?php

namespace Drupal\shp_backup\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\shp_backup\Service\Backup;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Upgrade an environment.
 *
 * @Action(
 *   id = "shp_upgrade_environment",
 *   label = @Translation("Upgrade environment"),
 *   confirm = TRUE,
 *   type = "node"
 * )
 */
class UpgradeEnvironment extends ViewsBulkOperationsActionBase implements PluginFormInterface, ContainerFactoryPluginInterface {

  /**
   * Used to start backups.
   *
   * @var \Drupal\shp_backup\Service\Backup
   */
  protected $backupService;

  /**
   * Constructs a BackupEnvironment object.
   *
   * @param mixed[] $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\shp_backup\Service\Backup $backup
   *   Backup service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Backup $backup) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->backupService = $backup;

    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('shp_backup.backup')
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
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['version'] = [
      '#type' => 'textfield',
      '#title' => t('Version'),
      '#default_value' => $this->configuration['version'] ?? 'master',
      '#required' => TRUE,
      '#description' => t('The version to upgrade to.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['version'] = $form_state->getValue('version');
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    // Safety net against non environment nodes.
    if (!$entity || $entity->bundle() !== 'shp_environment') {
      $this->messenger()->addError('Upgrades can only be run against Environment nodes.');
      return;
    }
    if (!$site = $entity->field_shp_site->entity) {
      $this->messenger()->addError('No site found for @name.', ['@name' => $entity->label()]);
      return;
    }
    $this->backupService->upgrade($site, $entity, $this->configuration['version']);
  }

}
