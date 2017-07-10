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
class EnvironmentCloneForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shp_custom_environment_clone_form';
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
    return t('Clone environment - @site_title : @environment_title', ['@site_title' => $site->getTitle(), '@environment_title' => $environment->getTitle()]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $site = NULL, NodeInterface $environment = NULL) {
    $form_state->set('environment', $environment);

    // @todo - Get the backups.
    $backups = \Drupal::service('shp_custom.backup')->get($site->id(), $environment->id());
    $backup_times = [];
    if (isset($backups) && !is_null($backups)) {
      foreach ($backups as $backup) {
        $backup_times[$backup] = \Drupal::service('date.formatter')->format($backup);
      }
    }

    $build = [
      'intro' => [
        '#markup' => $this->t('Clone this environment to another'),
      ],
      'title' => [
        '#type' => 'textfield',
        '#title' => $this->t('Title'),
        '#size' => 60,
        '#maxlength' => 255,
        '#required' => TRUE,
      ],
      'existing_title' => [
        '#type' => 'textfield',
        '#title' => $this->t('Existing Environment Name'),
        '#size' => 60,
        '#maxlength' => 255,
        '#disabled' => TRUE,
        '#default_value' => $environment->getTitle(),
      ],
      'backup_timestamp' => [
        '#type' => 'select',
        '#title' => $this->t('Backup date'),
        '#options' => $backup_times,
        '#required' => TRUE,
      ],
      'field_shp_git_reference' => [
        '#type' => 'textfield',
        '#title' => $this->t('Git tag/branch'),
        '#default_value' => $environment->field_shp_git_reference->value,
        '#size' => 60,
        '#maxlength' => 50,
        '#required' => TRUE,
      ],
      'field_shp_domain_name' => [
        '#type' => 'value',
        '#value' => $site->field_shp_domain->value,
      ],
      'field_shp_environment_status' => [
        '#type' => 'value',
        '#value' => '0',
      ],
      'field_shp_site' => [
        '#type' => 'value',
        '#value' => $site->id(),
      ],
      'type' => [
        '#type' => 'value',
        '#value' => 'shp_environment',
      ],
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Clone'),
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->cleanValues();
    $input = $form_state->getValues();

    // Pass through the source environment id, to trigger the clone job on save.
    $input['previous_env_id'] = $form_state->get('environment')->id();

    // Load parent site to get its domain.
    $site = Node::load($input['field_shp_site']);

    // Assign a unique domain name.
    $input['field_shp_domain_name'] = \Drupal::service('shp_custom.hosts_config')
      ->generateDomainForEnv($site, $input['field_shp_machine_name']);

    $environment = Node::create($input);
    $environment->validate();
    $environment->save();

    drupal_set_message($this->t('Successfully cloned %old_title environment to %new_title.', [
      '%old_title' => $form_state->get('environment')->getTitle(),
      '%new_title' => $input['title'],
    ]));

  }

}
