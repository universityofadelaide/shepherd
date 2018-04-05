<?php

namespace Drupal\shp_backup\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\node\NodeInterface;
use Drupal\shp_backup\Service\Backup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * EnvironmentRestoreForm.
 *
 * Triggers a restore from a saved backup to a sites environment.
 *
 * @package Drupal\shp_backup\Form
 */
class EnvironmentRestoreForm extends FormBase {

  /**
   * Used to trigger restores via the backup service.
   *
   * @var \Drupal\shp_backup\Service\Backup
   */
  protected $backup;

  /**
   * Messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * EnvironmentRestoreForm constructor.
   *
   * @param \Drupal\shp_backup\Service\Backup $backup
   *   The backup service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct(Backup $backup, EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger) {
    $this->backup            = $backup;
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger         = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('shp_backup.backup'),
      $container->get('entity_type.manager'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shp_backup_environment_restore_form';
  }

  /**
   * Callback to get page title for the name of the site.
   *
   * @param \Drupal\node\NodeInterface $site
   *   Site node.
   * @param \Drupal\node\NodeInterface $environment
   *   Environment node.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Translated markup.
   */
  public function getPageTitle(NodeInterface $site, NodeInterface $environment) {
    return t('Restore environment - @site_title : @environment_title', ['@site_title' => $site->getTitle(), '@environment_title' => $environment->getTitle()]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $site = NULL, NodeInterface $environment = NULL) {
    $form_state->set('site', $site);
    $form_state->set('environment', $environment);

    $backups = $this->backup->getAll($site);

    $backup_options = [];

    foreach ($backups as $backup) {
      $backup_options[$backup->nid] = $backup->_entity->title->value;
    }

    $build = [
      'backup' => [
        '#type' => 'select',
        '#title' => $this->t('Backup path'),
        '#options' => $backup_options,
        '#required' => TRUE,
      ],
      'actions' => [
        '#type' => 'actions',
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Restore now'),
        ],
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Shepherd: Completely refactor restore for shepherd.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->cleanValues();

    $site = $form_state->get('site');
    $environment = $form_state->get('environment');

    $backup = $this->entityTypeManager->getStorage('node')->load($form_state->getValue('backup'));

    // Set the backup to restore from.
    $status = $this->backup->restore($backup, $environment);

    if ($status) {
      $this->messenger->addStatus($this->t('Restore has been queued for %title', [
        '%title' => $environment->getTitle(),
      ]));
    }
    else {
      $this->messenger->addError($this->t('Restore failed. Could not find any instances for %title',
        [
          '%title' => $environment->getTitle(),
        ]));
    }

    $form_state->setRedirect("entity.node.canonical", ['node' => $site->id()]);
  }

}
