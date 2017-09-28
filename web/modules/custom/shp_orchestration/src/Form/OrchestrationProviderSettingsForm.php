<?php

namespace Drupal\shp_orchestration\Form;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class OrchestrationProviderSettingsForm.
 *
 * @package Drupal\shp_orchestration\Form
 */
class OrchestrationProviderSettingsForm extends ConfigFormBase {

  protected $orchestrationProviderManager;

  protected $entityTypeManager;

  /**
   * OrchestrationProviderSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   ConfigFactoryInterface.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $orchestration_provider_manager
   *   PluginManagerInterface.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   EntityTypeManagerInterface.
   */
  public function __construct(ConfigFactoryInterface $config_factory,
                              PluginManagerInterface $orchestration_provider_manager,
                              EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($config_factory);

    $this->orchestrationProviderManager = $orchestration_provider_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static (
      $container->get('config.factory'),
      $container->get('plugin.manager.orchestration_provider'),
      $container->get('entity_type.manager')
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
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
