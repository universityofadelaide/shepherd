<?php
/**
 * @file
 * Contains \Drupal\ua_site_jenkins\Form\JenkinsSettings.
 */

namespace Drupal\ua_site_jenkins\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class JenkinsSettings
 * @package Drupal\ua_site_jenkins\Form
 */
class JenkinsSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'ua_site_jenkins_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
      $config = $this->config('ua_site_jenkins.settings');

      $form['server'] = [
        '#type' => 'details',
        '#title' => $this->t('Jenkins Build Server'),
        '#open' => TRUE,
        '#tree' => TRUE,
      ];
      $form['server']['path'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Path'),
        '#default_value' => $config->get('server.path'),
      ];
      $form['server']['token'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Token'),
        '#default_value' => $config->get('server.token'),
      ];
      $form['server']['job'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Job'),
        '#default_value' => $config->get('server.job'),
      ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state ) {
    $config = $this->config('ua_site_jenkins.settings');

    // @todo Validate this data !
    $data = $form_state->getValue('server');
    $config->set('server.path', $data['path'])
      ->set('server.token', $data['token'])
      ->set('server.job', $data['job']);
    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ua_site_jenkins.settings'];
  }
}
