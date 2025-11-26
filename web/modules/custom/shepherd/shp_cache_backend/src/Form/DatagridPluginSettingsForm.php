<?php

namespace Drupal\shp_cache_backend\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for orchestration providers.
 */
class DatagridPluginSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['shp_cache_backend.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'shp_cache_backend_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('shp_cache_backend.settings');

    $form['namespace'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Datagrid namespace'),
      '#default_value' => $config->get('namespace'),
      '#description' => $this->t("The OpenShift project containing the datagrid cache."),
      '#required' => FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Save state.
    $config = $this->config('shp_cache_backend.settings');
    $config->set('namespace', $form_state->getValue('namespace'));
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
