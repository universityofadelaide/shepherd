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

    $form['backup_service']['backup_command'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Backup command'),
      '#description' => 'Commands to run to backup a site. Each command on a new line will be combined with && when run.',
      '#default_value' => $config->get('backup_service.backup_command'),
    ];

    $form['backup_service']['restore_command'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Restore command'),
      '#description' => 'Commands to run to restore a site. Each command on a new line will be combined with && when run.',
      '#default_value' => $config->get('backup_service.restore_command'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('shp_custom.settings');
    $config->delete();

    $values = $form_state->getValue('backup_service');
    $config->set('backup_service.backup_command', $values['backup_command']);
    $config->set('backup_service.restore_command', $values['restore_command']);
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
