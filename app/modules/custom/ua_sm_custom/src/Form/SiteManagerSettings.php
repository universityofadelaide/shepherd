<?php
/**
 * @file
 * Contains \Drupal\ua_sm_custom\Form\SiteManagerSettings.
 */

namespace Drupal\ua_sm_custom\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * A form to set config for the Site Manager and its integrations.
 */
class SiteManagerSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'ua_sm_custom_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ua_sm_custom.settings');

    $form['jenkins'] = [
      '#type' => 'details',
      '#title' => $this->t('Jenkins Build Integration'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $form['jenkins']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#size' => 30,
      '#description' => $this->t('Trigger a build on Jenkins when a site instance is created.'),
      '#default_value' => $config->get('jenkins.enabled'),
    ];
    $form['jenkins']['base_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Base URI'),
      '#description' => $this->t('The base URI of Jenkins, excluding the job name.'),
      '#default_value' => $config->get('jenkins.base_uri'),
    ];
    $form['jenkins']['token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token'),
      '#description' => $this->t('Token to use for authentication with Jenkins.'),
      '#default_value' => $config->get('jenkins.token'),
    ];
    $form['jenkins']['provision_job'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Provision Job'),
      '#description' => $this->t('The job to trigger when a site instance is created.'),
      '#default_value' => $config->get('jenkins.provision_job'),
    ];
    $form['jenkins']['deploy_job'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Deploy Job'),
      '#description' => $this->t('The job to trigger when a site instance is deployed.'),
      '#default_value' => $config->get('jenkins.deploy_job'),
    ];
    $form['jenkins']['decommission_job'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Decommission Job'),
      '#description' => $this->t('The job to trigger when a site instance is decommissioned.'),
      '#default_value' => $config->get('jenkins.decommission_job'),
    ];
    $form['jenkins']['destroy_job'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Destroy Job'),
      '#description' => $this->t('The job to trigger when a site instance is destroyed.'),
      '#default_value' => $config->get('jenkins.destroy_job'),
    ];
    $form['jenkins']['backup_job'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Backup Job'),
      '#description' => $this->t('The job to trigger when the backup cron calls on a site instance.'),
      '#default_value' => $config->get('jenkins.backup_job'),
    ];
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
      '#description' => $this->t('When checked, Site Manager will attempt to synchronise users and sites with LDAP.'),
      '#default_value' => $config->get('ldap.enabled'),
    ];

    $form['controlled_roles'] = [
      '#type' => 'details',
      '#title' => $this->t('Controlled Roles'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $controlled_roles = '';
    foreach ($config->get('controlled_roles') as $key => $val) {
      $controlled_roles .= $key . '|' . $val . "\n";
    }

    $form['controlled_roles']['textarea'] = [
      '#type' => 'textarea',
      '#rows' => 10,
      '#description' => $this->t('Setup your controlled roles using the format <i>role|description</i>'),
      '#default_value' => $controlled_roles,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('ua_sm_custom.settings');
    $controlled_roles_data = explode("\r\n", trim($form_state->getValue('controlled_roles')['textarea']));
    $config->delete('controlled_roles');

    foreach ($controlled_roles_data as $role) {
      $controlled_role = explode('|', $role);
      if (trim($controlled_role[0]) != '' && trim($controlled_role[1]) != '') {
        $config->set('controlled_roles.' . str_replace(' ', '_', trim($controlled_role[0])), trim($controlled_role[1]));
      }
    }

    // @todo Validate this data !
    $jenkins_data = $form_state->getValue('jenkins');
    $config
      ->set('jenkins.enabled', $jenkins_data['enabled'])
      ->set('jenkins.base_uri', $jenkins_data['base_uri'])
      ->set('jenkins.token', $jenkins_data['token'])
      ->set('jenkins.provision_job', $jenkins_data['provision_job'])
      ->set('jenkins.deploy_job', $jenkins_data['deploy_job'])
      ->set('jenkins.decommission_job', $jenkins_data['decommission_job'])
      ->set('jenkins.destroy_job', $jenkins_data['destroy_job']);
    $config
      ->set('ldap.enabled', $form_state->getValue('ldap')['enabled']);
    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ua_sm_custom.settings'];
  }

}
