<?php

namespace Drupal\shp_custom\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Class SiteCloneForm.
 *
 * @package Drupal\shp_custom\Form
 */
class EnvironmentBackupForm extends FormBase {

  // @todo Remove this crud.
  const MACHINE_NAMES = [
    'dev' => 'DEV',
    'uat' => 'UAT',
    'prd' => 'PRD',
  ];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shp_custom_environment_backup_form';
  }

  /**
   * Callback to get page title for the name of the site.
   *
   * @param \Drupal\node\NodeInterface $site
   *   Site node.
   * @param \Drupal\node\NodeInterface $environment
   *   Evnironment node.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Translated markup.
   */
  public function getPageTitle(NodeInterface $site, NodeInterface $environment) {
    return t('Backup environment - @site_title : @environment_title', ['@site_title' => $site->getTitle(), '@environment_title' => $environment->getTitle()]);
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
      ->condition('type', 'shp_site_instance')
      ->condition('field_shp_environment', $form_state->get('environment')->Id())
      ->execute();

    if (count($instance_ids)) {
      // Run the backup from an arbitrary (first returned) instance.
      $instance = Node::load(current($instance_ids));
      \Drupal::service('shp_custom.backup')->createBackup($instance);

      drupal_set_message($this->t('Backup has been queued for %title', [
        '%title' => $form_state->get('environment')->getTitle(),
      ]));
    }
    else {
      drupal_set_message($this->t('Could not find any instances to backup for %title', [
        '%title' => $form_state->get('environment')->getTitle(),
      ]));
    }

  }

}
