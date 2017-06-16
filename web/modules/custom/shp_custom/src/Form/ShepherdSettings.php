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
    $form['jenkins']['backup_job'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Backup Job'),
      '#description' => $this->t('The job to trigger when the backup cron calls on a site instance.'),
      '#default_value' => $config->get('jenkins.backup_job'),
    ];
    $form['jenkins']['clone_job'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Deploy Job'),
      '#description' => $this->t('The job to trigger when an environment is cloned.'),
      '#default_value' => $config->get('jenkins.clone_job'),
    ];
    $form['jenkins']['decommission_job'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Decommission Job'),
      '#description' => $this->t('The job to trigger when a site instance is decommissioned.'),
      '#default_value' => $config->get('jenkins.decommission_job'),
    ];
    $form['jenkins']['deploy_job'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Deploy Job'),
      '#description' => $this->t('The job to trigger when a site instance is deployed.'),
      '#default_value' => $config->get('jenkins.deploy_job'),
    ];
    $form['jenkins']['restore_job'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Restore Job'),
      '#description' => $this->t('The job to trigger when a clone/restore environment is created.'),
      '#default_value' => $config->get('jenkins.restore_job'),
    ];
    $form['jenkins']['reverse_proxy_job'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Reverse Proxy Job'),
      '#description' => $this->t('The job to trigger when the domain and paths of an environment are changed.'),
      '#default_value' => $config->get('jenkins.reverse_proxy_job'),
    ];

    $form['environment_domains'] = [
      '#type' => 'details',
      '#title' => $this->t('Environment domains'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $environment_domains = '';
    foreach ($config->get('environment_domains') as $key => $val) {
      $environment_domains .= $key . '|' . $val . "\n";
    }

    $form['environment_domains']['textarea'] = [
      '#type' => 'textarea',
      '#rows' => 10,
      '#description' => $this->t('Setup the domain each each environment corresponds using the format environment|domain.'),
      '#default_value' => $environment_domains,
    ];

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

    $environment_domains_data = explode("\r\n", trim($form_state->getValue('environment_domains')['textarea']));
    foreach ($environment_domains_data as $environment_domain) {
      list($environment, $domain) = explode('|', $environment_domain);
      if (trim($environment) != '' && trim($domain) != '') {
        $config->set('environment_domains.' . str_replace(' ', '-', trim($environment)), trim($domain));
      }
    }

    // @todo Validate this data !
    $jenkins_data = $form_state->getValue('jenkins');
    $config
      ->set('jenkins.enabled', $jenkins_data['enabled'])
      ->set('jenkins.base_uri', $jenkins_data['base_uri'])
      ->set('jenkins.token', $jenkins_data['token'])
      ->set('jenkins.backup_job', $jenkins_data['backup_job'])
      ->set('jenkins.clone_job', $jenkins_data['clone_job'])
      ->set('jenkins.decommission_job', $jenkins_data['decommission_job'])
      ->set('jenkins.deploy_job', $jenkins_data['deploy_job'])
      ->set('jenkins.restore_job', $jenkins_data['restore_job'])
      ->set('jenkins.reverse_proxy_job', $jenkins_data['reverse_proxy_job']);
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
