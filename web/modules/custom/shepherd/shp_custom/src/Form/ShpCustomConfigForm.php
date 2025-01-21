<?php

namespace Drupal\shp_custom\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a confirmation form before clearing out the examples.
 */
class ShpCustomConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shp_custom_shp_custom_config';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['shp_custom.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['environment_creation_time_delay'] = [
      '#type' => 'number',
      '#title' => $this->t('Time delay'),
      '#default_value' => $this->config('shp_custom.settings')->get('environment_creation_time_delay'),
      '#description' => $this->t('Time delay is the number of seconds after an environment creation before a user is allowed to trigger a new build or restore'),
    ];
    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('shp_custom.settings');
    $config->set('environment_creation_time_delay', $form_state->getValue('environment_creation_time_delay'));
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
