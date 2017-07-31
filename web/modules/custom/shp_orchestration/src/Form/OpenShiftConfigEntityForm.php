<?php

namespace Drupal\shp_orchestration\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * {@inheritdoc}
 */
class OpenShiftConfigEntityForm extends EntityForm {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $entity = $this->entity;

    $form['id'] = array(
      '#type' => 'value',
      '#value' => 'openshift',
    );
    $form['endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Endpoint'),
      '#default_value' => $entity->endpoint,
      '#required' => TRUE,
    ];
    $form['token'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Token'),
      '#default_value' => $entity->token,
      '#attributes' => ['autocomplete' => 'off'],
      '#required' => TRUE,
    ];
    $form['verify_tls'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Verify TLS'),
      '#default_value' => $entity->verify_tls,
    ];
    $form['namespace'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Namespace'),
      '#default_value' => $entity->namespace,
      '#required' => FALSE,
    ];
    $form['uid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User ID'),
      '#description' => $this->t("The default user id containers should run as."),
      '#default_value' => $entity->uid,
      '#required' => FALSE,
    ];
    $form['gid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Group ID'),
      '#description' => $this->t("The default group id containers should run as."),
      '#default_value' => $entity->gid,
      '#required' => FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = $entity->save();
    if ($status == SAVED_UPDATED) {
      drupal_set_message($this->t('OpenShift configuration has been updated'));
    }
    else {
      drupal_set_message($this->t('OpenShift configuration has been saved'));
    }
  }

}
