<?php

namespace Drupal\shp_database_provisioner\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Database provisioner settings.
 */
class SettingsForm extends ConfigFormBase {

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
      ->save();

    parent::submitForm($form, $form_state);
  }

}
