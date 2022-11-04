<?php

namespace Drupal\shp_database_provisioner\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Renderer;
use Drupal\shp_custom\Service\StringGenerator;
use Drupal\shp_database_provisioner\Service\Provisioner;
use Drupal\shp_orchestration\OrchestrationProviderPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Database provisioner settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Database provisioner.
   *
   * @var \Drupal\shp_database_provisioner\Service\Provisioner
   */
  protected $provisioner;

  /**
   * Random string generator.
   *
   * @var \Drupal\shp_custom\Service\StringGenerator
   */
  protected $stringGenerator;

  /**
   * Orchestration provider.
   *
   * @var object
   */
  protected $orchestrationProvider;

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
   * @param \Drupal\shp_database_provisioner\Service\Provisioner $provisioner
   *   Database provisioner.
   * @param \Drupal\shp_custom\Service\StringGenerator $string_generator
   *   String generator.
   * @param \Drupal\shp_orchestration\OrchestrationProviderPluginManagerInterface $orchestration_provider_plugin_manager
   *   Orchestration provider plugin manager.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   Drupal renderer.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Provisioner $provisioner, StringGenerator $string_generator, OrchestrationProviderPluginManagerInterface $orchestration_provider_plugin_manager, Renderer $renderer) {
    parent::__construct($config_factory);
    $this->provisioner = $provisioner;
    $this->stringGenerator = $string_generator;
    $this->orchestrationProvider = $orchestration_provider_plugin_manager->getProviderInstance();
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('shp_database_provisioner.provisioner'),
      $container->get('shp_custom.string_generator'),
      $container->get('plugin.manager.orchestration_provider'),
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
    $form['options'] = [
      '#type' => 'textarea',
      '#title' => t('Options'),
      '#description' => t('A list of <a href=":user_resources_url">user resource</a> limit options. Enter one kay-value pair per line, in the format MAX_USER_CONNECTIONS 20',
        [':user_resources_url' => 'https://dev.mysql.com/doc/refman/8.0/en/user-resources.html']),
      '#default_value' => $config->get('options'),
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
      ->set('options', $form_state->getValue(['options']))
      ->set('populate_command', $form_state->getValue(['populate_command']))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Attempt to create a test user and notify user if there's a problem.
    $privileged_password = $this->orchestrationProvider->getSecret(0, $form_state->getValue(['secret']),
      'DATABASE_PASSWORD');
    $db = new \mysqli(
      $form_state->getValue(['host']),
      $form_state->getValue(['user']),
      $privileged_password,
      NULL,
      $form_state->getValue(['port']),
      NULL
    );
    $test_database = 'shepherd_test';
    // Create a user and database, and clean up.
    $success = $this->provisioner->createDatabase($test_database, $db) &&
      $this->provisioner->createUser($test_database, $test_database, $this->stringGenerator->generateRandomPassword(), $db, $form_state->getValue('options')) &&
      $this->provisioner->dropDatabase($test_database, $db) &&
      $this->provisioner->dropUser($test_database, $db);

    if ($success) {
      $this->messenger()->addStatus(t('Successfully connected to the database.'));
    }
    else {
      $form_state->setError($form, 'Could not create a test database and user. Confirm the secret exists and details are correct.');
      // Always try to clean up.
      $this->provisioner->dropDatabase($test_database, $db) ||
      $this->provisioner->dropUser($test_database, $db);
    }
    parent::validateForm($form, $form_state);
  }

}
