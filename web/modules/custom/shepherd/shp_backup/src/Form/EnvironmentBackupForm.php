<?php

namespace Drupal\shp_backup\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\shp_backup\Service\Backup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * EnvironmentBackupForm.
 *
 * Triggers backups of a sites environment.
 *
 * @package Drupal\shp_backup\Form
 */
class EnvironmentBackupForm extends FormBase {

  use StringTranslationTrait;

  /**
   * Used to start backups.
   *
   * @var \Drupal\shp_backup\Service\Backup
   */
  protected $backup;

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
   * Messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * EnvironmentBackupForm constructor.
   *
   * @param \Drupal\shp_backup\Service\Backup $backup
   *   Backup service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   Date formatter service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct(Backup $backup, TimeInterface $time, DateFormatterInterface $date_formatter, MessengerInterface $messenger) {
    $this->backup = $backup;
    $this->time = $time;
    $this->dateFormatter = $date_formatter;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('shp_backup.backup'),
      $container->get('datetime.time'),
      $container->get('date.formatter'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shp_backup_environment_backup_form';
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
    return $this->t('Backup environment - @site_title : @environment_title', [
      '@site_title' => $site->getTitle(),
      '@environment_title' => $environment->getTitle(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $site = NULL, NodeInterface $environment = NULL) {
    $form_state->set('site', $site);
    $form_state->set('environment', $environment);

    $form['backup_name'] = [
      '#title' => $this->t('Backup name'),
      '#description' => $this->t('Optionally set a friendly name for this backup. Once submitted, you can visit the Backups tab for this site to view the status of the backup.'),
      '#type' => 'textfield',
      '#default_value' => $this->dateFormatter->format($this->time->getRequestTime(), 'medium'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Backup now'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $site = $form_state->get('site');
    $environment = $form_state->get('environment');

    // Call the backup service to start a backup and update the backup node.
    if ($this->backup->createBackup($site, $environment, $form_state->getValue('backup_name'))) {
      $this->messenger->addStatus($this->t('Backup has been queued for %title', [
        '%title' => $form_state->get('environment')->getTitle(),
      ]));
    }
    else {
      $this->messenger->addError($this->t('Backup failed for %title', [
        '%title' => $form_state->get('environment')->getTitle(),
      ]));
    }

    $form_state->setRedirectUrl($site->toUrl());
  }

}
