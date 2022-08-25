<?php

namespace Drupal\shp_orchestration\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\shp_orchestration\OrchestrationProviderPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for orchestration providers.
 */
class OrchestrationProviderSettingsForm extends ConfigFormBase {

  /**
   * The OS provider manager.
   *
   * @var \Drupal\shp_orchestration\OrchestrationProviderPluginManagerInterface
   */
  protected $orchestrationProviderManager;

  /**
   * OrchestrationProviderSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   ConfigFactoryInterface.
   * @param \Drupal\shp_orchestration\OrchestrationProviderPluginManagerInterface $orchestration_provider_manager
   *   PluginManagerInterface.
   */
  public function __construct(ConfigFactoryInterface $config_factory, OrchestrationProviderPluginManagerInterface $orchestration_provider_manager) {
    parent::__construct($config_factory);
    $this->orchestrationProviderManager = $orchestration_provider_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.orchestration_provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['shp_orchestration.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'orchestration_provider_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('shp_orchestration.settings');
    $plugins = $this->orchestrationProviderManager->getDefinitions();

    $form['provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Select an orchestration provider'),
      '#required' => TRUE,
      '#default_value' => $config->get('selected_provider'),
    ];
    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced'),
      '#open' => FALSE,
    ];
    $form['advanced']['queued_operations'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable queued operations. Ensures multiple actions are not executed concurrently for a given environment.'),
      '#default_value' => $config->get('queued_operations'),
    ];

    foreach ($plugins as $id => $plugin) {
      $form['provider']['#options'][$id] = $plugin['name'];
    }

    $form['connection'] = [
      '#type' => 'details',
      '#title' => $this->t('Connection'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];
    $form['connection']['endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Endpoint'),
      '#default_value' => $config->get('connection.endpoint'),
      '#required' => TRUE,
    ];
    $form['connection']['verify_tls'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Verify TLS'),
      '#default_value' => $config->get('connection.verify_tls'),
    ];
    $form['connection']['token'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Token'),
      '#default_value' => $config->get('connection.token'),
      '#attributes' => ['autocomplete' => 'off'],
      '#required' => TRUE,
    ];
    $form['connection']['namespace'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Project'),
      '#default_value' => $config->get('connection.namespace'),
      '#description' => $this->t("The OpenShift project to use."),
      '#required' => FALSE,
    ];
    $form['connection']['site_deploy_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site (Openshift Project) deploy prefix'),
      '#default_value' => $config->get('connection.site_deploy_prefix'),
      '#description' => $this->t("The prefix for newly deployed sites which are Openshift Projects."),
      '#required' => FALSE,
    ];
    $form['connection']['uid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User ID'),
      '#description' => $this->t("The default user id containers should run as."),
      '#default_value' => $config->get('connection.uid'),
      '#required' => FALSE,
    ];
    $form['connection']['gid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Group ID'),
      '#description' => $this->t("The default group id containers should run as."),
      '#default_value' => $config->get('connection.gid'),
      '#required' => FALSE,
    ];
    $form['connection']['admin_users'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Additional admin users'),
      '#default_value' => $config->get('connection.admin_users'),
      '#attributes' => ['autocomplete' => 'off'],
      '#required' => TRUE,
      '#description' => $this->t("Any additional users to add to new projects as admins. Usernames separated by comma, eg bob,jim,frank"),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save state.
    $config = $this->config('shp_orchestration.settings');
    $config->set('selected_provider', $form_state->getValue('provider'));
    $config->set('queued_operations', $form_state->getValue('queued_operations'));
    $config->set('connection', $form_state->getValue('connection'));
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
