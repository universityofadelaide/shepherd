<?php

namespace Drupal\shp_custom\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * EnvironmentPromoteForm.
 *
 * Allows promoting environments.
 *
 * @package Drupal\shp_custom\Form
 */
class EnvironmentPromoteForm extends FormBase {

  use StringTranslationTrait;

  /**
   * For retrieving config.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $config;

  /**
   * Used to identify who is creating the backup.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $current_user;

  /**
   * EnvironmentBackupForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Config Factory.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user.
   */
  public function __construct(ConfigFactoryInterface $config, AccountInterface $current_user) {
    $this->config = $config;
    $this->current_user = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shp_custom_environment_promote_form';
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
    return $this->t('Promote environment - @site_title : @environment_title', ['@site_title' => $site->getTitle(), '@environment_title' => $environment->getTitle()]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $site = NULL, NodeInterface $environment = NULL) {
    $form_state->set('site', $site);
    $form_state->set('environment', $environment);

    $form['exclusive'] = [
      '#title' => $this->t('Make this environment the exclusive destination for traffic?'),
      '#type' => 'checkbox',
      '#options' => ['exclusive' => TRUE, 'additional' => FALSE],
      '#default_value' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Promote now'),
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

    if ($result = \Drupal::service('shp_orchestration.environment')->promote($environment)) {

      drupal_set_message($this->t('Promoted %environment for %site successfully', [
        '%environment' => $environment->getTitle(),
        '%site' => $site->getTitle(),
      ]));
    }
    else {
      drupal_set_message($this->t('Failed to promote %environment for %site',
        [
          '%environment' => $environment->getTitle(),
          '%site' => $site->getTitle(),
        ]), 'error');
    }

    $form_state->setRedirect("entity.node.canonical", ['node' => $site->id()]);
  }

}
