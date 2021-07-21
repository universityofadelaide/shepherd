<?php

namespace Drupal\shp_backup\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\shp_backup\Service\Backup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Trigger an upgrade of a site's environment.
 *
 * @package Drupal\shp_backup\Form
 */
class EnvironmentUpgradeForm extends FormBase {

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
      $container->get('messenger'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shp_backup_environment_upgrade_form';
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
    return $this->t('Upgrade environment - @site_title : @environment_title', [
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

    $project = $site->field_shp_project->entity;

    $form['version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#description' => $this->t('The version to upgrade to.'),
      '#required' => TRUE,
      '#default_value' => $project->field_shp_git_default_ref->value,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Upgrade'),
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
    if ($new_env = $this->backup->upgrade($site, $environment, $form_state->getValue('version'))) {
      $this->messenger->addStatus($this->t('Upgrade has been queued, new environment is being synced %title', [
        '%title' => $new_env->getTitle(),
      ]));
    }
    else {
      $this->messenger->addError($this->t('Upgrade failed for %title', [
        '%title' => $environment->getTitle(),
      ]));
    }
    $form_state->setRedirectUrl($site->toUrl());
  }

}
