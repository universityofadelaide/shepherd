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
    $form['jenkins']['enabled'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enabled'),
      '#size' => 30,
      '#description' => t('Trigger a build on Jenkins when a site instance is created.'),
      '#default_value' => $config->get('jenkins.enabled'),
    );
    $form['jenkins']['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path'),
      '#description' => $this->t('The complete path to Jenkins, excluding the job name.'),
      '#default_value' => $config->get('jenkins.path'),
    ];
    $form['jenkins']['token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token'),
      '#description' => $this->t('Token to use for authentication with Jenkins.'),
      '#default_value' => $config->get('jenkins.token'),
    ];
    $form['jenkins']['job'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Job'),
      '#description' => $this->t('The name of the job to trigger when a site instance is created.'),
      '#default_value' => $config->get('jenkins.job'),
    ];
    $form['ldap'] = [
      '#type' => 'details',
      '#title' => $this->t('LDAP Integration'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $form['ldap']['enabled'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enabled'),
      '#size' => 30,
      '#description' => t('When checked, Site Manager will attempt to synchronize users and sites with LDAP.'),
      '#default_value' => $config->get('ldap.enabled'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('ua_sm_custom.settings');

    // @todo Validate this data !
    $jenkins_data = $form_state->getValue('jenkins');
    $config
      ->set('jenkins.enabled', $jenkins_data['enabled'])
      ->set('jenkins.path', $jenkins_data['path'])
      ->set('jenkins.token', $jenkins_data['token'])
      ->set('jenkins.job', $jenkins_data['job']);
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
