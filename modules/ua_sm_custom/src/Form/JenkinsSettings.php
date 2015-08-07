<?php
/**
 * @file
 * Contains \Drupal\ua_sm_custom\Form\JenkinsSettings.
 */

namespace Drupal\ua_sm_custom\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * A form to set config for the jenkins build server.
 */
class JenkinsSettings extends ConfigFormBase {

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
    $config = $this->config('ua_sm_custom.settings');

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
    return ['ua_sm_custom.settings'];
  }
}
