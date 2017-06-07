<?php

namespace Drupal\shp_ldap_sync\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * A form to set config for the Shepherd and its integrations.
 */
class LdapSyncSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'shp_ldap_sync_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('shp_ldap_sync.settings');

    $form['ldap'] = [
      '#type' => 'details',
      '#title' => $this->t('LDAP Integration'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $form['ldap']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#size' => 30,
      '#description' => $this->t('Synchronise users and their roles for each site to LDAP.'),
      '#default_value' => $config->get('enabled'),
    ];

    $role_options = array_map('\Drupal\Component\Utility\Html::escape', user_role_names());
    $form['ldap']['controlled_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Controlled Roles'),
      '#options' => $role_options,
      '#default_value' => $config->get('controlled_roles'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('shp_ldap_sync.settings');

    $config->set('enabled', $form_state->getValue('ldap')['enabled']);
    $config->set('controlled_roles', array_filter($form_state->getValue('ldap')['controlled_roles']));

    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['shp_ldap_sync.settings'];
  }

}
