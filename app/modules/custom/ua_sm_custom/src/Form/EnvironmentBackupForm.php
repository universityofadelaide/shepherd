<?php

namespace Drupal\ua_sm_custom\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Class SiteCloneForm.
 *
 * @package Drupal\ua_sm_custom\Form
 */
class EnvironmentBackupForm extends FormBase {

  const MACHINE_NAMES = [
    'dev' => 'DEV',
    'uat' => 'UAT',
    'prd' => 'PRD',
  ];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ua_sm_custom_environment_backup_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $site = NULL, NodeInterface $environment = NULL) {
    $form_state->set('environment', $environment);

    $build = [
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Backup now'),
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->cleanValues();
    // Find instances relating to the environment.
    $instance_ids = \Drupal::entityQuery('node')
      ->condition('type', 'ua_sm_site_instance')
      ->condition('field_ua_sm_environment', $form_state->get('environment')->Id())
      ->execute();

    if (count($instance_ids)) {
      // Run the backup from an arbitrary (first returned) instance.
      $instance = Node::load(current($instance_ids));
      \Drupal::service('ua_sm_custom.backup')->createBackup($instance);

      drupal_set_message($this->t('Backup has been queued for %title', [
        '%title' => $form_state->get('environment')->getTitle(),
      ]));
    }
    else {
      drupal_set_message($this->t('Could not find any instances to backup for %title', [
        '%title' => $form_state->get('environment')->getTitle(),
      ]));
    }

    // Redirect back to where we started from. The site's environment page.
    $form_state->setRedirect(
      'view.ua_sm_site_environments.page_1',
      ['node' => $form_state->get('environment')->field_ua_sm_site->target_id]
    );
  }

}
