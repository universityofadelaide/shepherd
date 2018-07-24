<?php

namespace Drupal\shp_backup\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\shp_backup\Service\Backup;
use Drupal\token\TokenInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * EnvironmentBackupForm.
 *
 * Triggers backups of a sites environment.
 *
 * @package Drupal\shp_backup\Form
 */
class EnvironmentBackupForm extends FormBase {

  /**
   * For retrieving config.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $config;

  /**
   * Used to start backups.
   *
   * @var \Drupal\shp_backup\Service\Backup
   */
  protected $backup;

  /**
   * Used to replace text within parameters.
   *
   * @var \Drupal\token\Token
   */
  protected $token;

  /**
   * Messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * EnvironmentBackupForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Config Factory.
   * @param \Drupal\shp_backup\Service\Backup $backup
   *   Backup service.
   * @param \Drupal\token\TokenInterface $token
   *   Token service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct(ConfigFactoryInterface $config, Backup $backup, TokenInterface $token, MessengerInterface $messenger) {
    $this->config    = $config;
    $this->backup    = $backup;
    $this->token     = $token;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('shp_backup.backup'),
      $container->get('token'),
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
    return $this->t('Backup environment - @site_title : @environment_title', ['@site_title' => $site->getTitle(), '@environment_title' => $environment->getTitle()]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $site = NULL, NodeInterface $environment = NULL) {
    $config = $this->config->get('shp_backup.settings');
    $backup_title = $this->token->replace($config->get('backup_title'), ['environment' => $environment]);

    $form_state->set('site', $site);
    $form_state->set('environment', $environment);

    $form['backup_title'] = [
      '#title' => $this->t('Backup identifier'),
      '#type' => 'textfield',
      '#default_value' => $backup_title,
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
    $form_state->cleanValues();

    $site = $form_state->get('site');
    $environment = $form_state->get('environment');

    // Call the backup service to start a backup and update the backup node.
    if ($this->backup->createNode($environment, $form_state->getValue('backup_title'))) {

      $this->messenger->addStatus($this->t('Backup has been queued for %title', [
        '%title' => $form_state->get('environment')->getTitle(),
      ]));
    }
    else {
      $this->messenger->addError($this->t('Backup failed for %title',
        [
          '%title' => $form_state->get('environment')->getTitle(),
        ]));
    }

    $form_state->setRedirect("entity.node.canonical", ['node' => $site->id()]);
  }

}
