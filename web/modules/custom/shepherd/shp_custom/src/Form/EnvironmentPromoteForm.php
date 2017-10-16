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
   * Used to identify who is performing the operation.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $current_user;

  /**
   * EnvironmentPromoteForm constructor.
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
    return $this->t('Promote environment to production');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $site = NULL, NodeInterface $environment = NULL) {
    $form_state->set('site', $site);
    $form_state->set('environment', $environment);

    // @todo Lookup existing prod and list on this form.

    $form['site'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site'),
      '#value' => $site->getTitle(),
      '#disabled' => TRUE,
    ];
    $form['production_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Production url'),
      '#value' => $site->field_shp_domain->value,
      '#disabled' => TRUE,
    ];
    $form['promote_environment'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Environment to promote'),
      '#value' => $environment->getTitle(),
      '#disabled' => TRUE,
    ];
    $form['warning'] = [
      '#type' => 'item',
      '#title' => $this->t('Warning'),
      '#description' => $this->t('Please ensure the new environment is production ready and you have been authorised to make the change.'),
    ];

    // @todo everything is exclusive for now, implement non-exclusive?
    //$form['exclusive'] = [
    //  '#title' => $this->t('Make this environment the exclusive destination?'),
    //  '#type' => 'checkbox',
    //  '#default_value' => FALSE,
    //];

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
    $exclusive = TRUE; // $form_state->getValue('exclusive');

    if ($result = \Drupal::service('shp_orchestration.environment')->promoted($site, $environment, $exclusive)) {
      drupal_set_message($this->t('Promoted %environment for %site successfully', [
        '%environment' => $environment->getTitle(),
        '%site' => $site->getTitle(),
      ]));
      \Drupal::service('shp_custom.site')->setGoLiveDate($environment);
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
