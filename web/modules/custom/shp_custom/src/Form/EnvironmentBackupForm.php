<?php

namespace Drupal\shp_custom\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
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
    // @todo: Shepherd: Instances are gone, need to backup some other way.
  }

}
