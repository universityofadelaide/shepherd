<?php

namespace Drupal\shp_database_provisioner\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Database provisioner settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Used to render the pretty tokenizer output.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * ShepherdSettings constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Renderer.
   */
  public function __construct(ConfigFactoryInterface $config_factory, RendererInterface $renderer) {
    parent::__construct($config_factory);

    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shp_database_provisioner_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['shp_database_provisioner.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('shp_database_provisioner.settings');

    $token_tree = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['shp_database_provisioner'],
    ];
    $rendered_token_tree = $this->renderer->render($token_tree);

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => t('Enabled'),
      '#size' => 30,
      '#description' => t('When checked, a database and user is provisioned when a new environment is created.'),
      '#default_value' => $config->get('enabled'),
    ];
    $form['host'] = [
      '#type' => 'textfield',
      '#title' => t('Host'),
      '#description' => t('The database host to provision DBs on.'),
      '#default_value' => $config->get('host'),
    ];
    $form['port'] = [
      '#type' => 'textfield',
      '#title' => t('Port'),
      '#description' => t('The database host port. Typically 3306.'),
      '#default_value' => $config->get('port'),
    ];
    $form['user'] = [
      '#type' => 'textfield',
      '#title' => t('User'),
      '#description' => t('The privileged user to use when connecting to the DB. Must have permissions for CREATE DATABASE and GRANT.'),
      '#default_value' => $config->get('user'),
    ];
    $form['secret'] = [
      '#type' => 'textfield',
      '#title' => t('Secret'),
      '#description' => t('The name of the secret in which the privileged user password is stored. Fetched from the orchestration provider.'),
      '#default_value' => $config->get('secret'),
    ];

    $form['populate_command'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Populate command'),
      '#description' => t('Commands to run to populate a database. Each command on a new line will be combined with && when run to stop on any error. This field supports tokens. @browse_tokens_link', ['@browse_tokens_link' => $rendered_token_tree]),
      '#default_value' => $config->get('populate_command'),
      '#element_validate' => [
        'token_element_validate',
      ],
      '#token_types' => [
        'shp_database_provisioner',
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('shp_database_provisioner.settings')
      ->set('enabled', $form_state->getValue(['enabled']))
      ->set('host', $form_state->getValue(['host']))
      ->set('port', $form_state->getValue(['port']))
      ->set('user', $form_state->getValue(['user']))
      ->set('secret', $form_state->getValue(['secret']))
      ->set('populate_command', $form_state->getValue(['populate_command']))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
