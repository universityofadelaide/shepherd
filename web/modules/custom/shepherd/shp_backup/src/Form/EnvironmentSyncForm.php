<?php

namespace Drupal\shp_backup\Form;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\node\NodeInterface;
use Drupal\shp_backup\Service\Backup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Triggers a sync from another environment.
 */
class EnvironmentSyncForm extends FormBase {

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
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * EnvironmentSyncForm constructor.
   *
   * @param \Drupal\shp_backup\Service\Backup $backup
   *   The backup service.
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_storage
   *   The node storage.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct(Backup $backup, EntityStorageInterface $node_storage, MessengerInterface $messenger) {
    $this->backup = $backup;
    $this->nodeStorage = $node_storage;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('shp_backup.backup'),
      $container->get('entity_type.manager')->getStorage('node'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shp_backup_environment_sync_form';
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
    return t('Sync environment - @site_title : @environment_title', ['@site_title' => $site->getTitle(), '@environment_title' => $environment->getTitle()]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $site = NULL, NodeInterface $environment = NULL) {
    $form_state->set('site', $site);
    $form_state->set('environment', $environment);

    $environment_ids = $this->nodeStorage->getQuery()
      ->condition('type', 'shp_environment')
      ->condition('field_shp_site', $site->id())
      ->condition('nid', $environment->id(), '<>')
      ->condition('status', 1)
      ->execute();

    if (empty($environment_ids)) {
      return [
        '#markup' => "<p>No other active environments to sync from.</p>",
      ];
    }

    $sync_options = [];

    foreach ($this->nodeStorage->loadMultiple($environment_ids) as $environment) {
      $sync_options[$environment->id()] = $environment->label();
    }

    $build = [
      'sync_from' => [
        '#type' => 'select',
        '#title' => $this->t('Sync from'),
        '#description' => $this->t('Choose an environment to sync the database and files from'),
        '#options' => $sync_options,
        '#required' => TRUE,
      ],
      'actions' => [
        '#type' => 'actions',
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Sync now'),
        ],
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\node\NodeInterface $site */
    $site = $form_state->get('site');
    $environment = $form_state->get('environment');

    if ($this->backup->sync($form_state->getValue('sync_from'), $environment->id())) {
      $this->messenger->addStatus($this->t('Sync has been triggered for %title', [
        '%title' => $environment->getTitle(),
      ]));
    }
    else {
      $this->messenger->addError($this->t('Sync failed for %title', [
        '%title' => $environment->getTitle(),
      ]));
    }

    $form_state->setRedirectUrl($site->toUrl());
  }

}
