<?php

namespace Drupal\shp_custom\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * A form to set config for the Shepherd and its integrations.
 */
class ShepherdSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shp_custom_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('shp_custom.settings');

    $form['backup_service'] = [
      '#type' => 'details',
      '#title' => $this->t('Backups'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $form['backup_service']['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path'),
      '#description' => 'Directory path to the backup directory, no trailing slash required.',
      '#default_value' => $config->get('backup_service.path'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('shp_custom.settings');
    $config->delete();

    // @todo Validate this data !
    $config
      ->set('backup_service.path', rtrim($form_state->getValue('backup_service')['path'], '/'));
    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['shp_custom.settings'];
  }

}
