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
    $form_state->set('site', $site);
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

    $distribution = Node::load($form_state->get('site')->field_shp_distribution->target_id);

    \Drupal::service('shp_custom.backup')->createBackup(
      $form_state->get('site'),
      $distribution->title->value,
      $form_state->get('environment')
  );
  }

}
