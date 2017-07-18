<?php

namespace Drupal\shp_backup\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\shp_backup\Service\Backup;
use Drupal\token\TokenInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SiteCloneForm.
 *
 * @package Drupal\shp_backup\Form
 */
class EnvironmentBackupForm extends FormBase {

  /**
   * @var \Drupal\Core\Config\ConfigFactory
   *   For retrieving config.
   */
  protected $config;

  /**
   * @var \Drupal\shp_backup\Service\Backup
   *   Used to start backups.
   */
  protected $backup;

  /**
   * @var \Drupal\token\Token
   *   Used to replace text within parameters.
   */
  protected $token;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   *   Used to identify who is creating the backup.
   */
  protected $current_user;

  /**
   * EnvironmentBackupForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   * @param \Drupal\shp_backup\Service\Backup $backup
   * @param \Drupal\token\TokenInterface $token
   * @param \Drupal\Core\Session\AccountInterface $current_user
   */
  public function __construct(ConfigFactoryInterface $config, Backup $backup, TokenInterface $token, AccountInterface $current_user) {
    $this->config = $config;
    $this->backup = $backup;
    $this->token = $token;
    $this->current_user = $current_user;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('shp_backup.backup'),
      $container->get('token'),
      $container->get('current_user')
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
   *   Evnironment node.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Translated markup.
   */
  public function getPageTitle(NodeInterface $site, NodeInterface $environment) {
    return t('Backup environment - @site_title : @environment_title', ['@site_title' => $site->getTitle(), '@environment_title' => $environment->getTitle()]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $site = NULL, NodeInterface $environment = NULL) {
    $config = $this->config->get('shp_backup.settings');
    $backup_title = $this->token->replace($config->get('backup_service.backup_title'), ['environment' => $environment]);

    $form_state->set('site', $site);
    $form_state->set('environment', $environment);

    $form['backup_title'] = [
      '#title' => $this->t('Backup identifier'),
      '#type' => 'textfield',
      '#default_value' => $backup_title,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Backup now'),
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

    // Create a backup node with most values.
    $backup_node = Node::create([
      'type'                     => 'shp_backup',
      'langcode'                 => 'en',
      'uid'                      => $this->current_user->id(),
      'status'                   => 1,
      'title'                    => $form_state->getValue('backup_title'),
      'field_shp_backup_path'    => [['value' => '']],
      'field_shp_site'           => [['target_id' => $site->id()]],
      'field_shp_environment'    => [['target_id' => $environment->id()]],
    ]);
    $backup_node->save();

    // Call the backup service to start a backup and update the backup node.
    if ($this->backup->createBackup($backup_node)) {
      drupal_set_message($this->t('Backup has been queued for %title', [
        '%title' => $form_state->get('environment')->getTitle(),
      ]));
    }
    else {
      drupal_set_message($this->t('Backup failed for %title',
        [
          '%title' => $form_state->get('environment')->getTitle(),
        ]), 'error');
    }

    $form_state->setRedirect("entity.node.canonical", ['node' => $site->id()]);
  }

}
