<?php

/**
 * @file
 * Contains \Drupal\ua_sm_custom\Form\EnvironmentCloneForm.
 */

namespace Drupal\ua_sm_custom\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use \DateTime;
use \DateTimeZone;

/**
 * Class SiteCloneForm.
 *
 * @package Drupal\ua_sm_custom\Form
 */
class EnvironmentCloneForm extends FormBase {

  const MACHINE_NAMES = [
    'dev' => 'DEV',
    'uat' => 'UAT',
    'prd' => 'PRD',
  ];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ua_sm_custom_environment_clone_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $site = NULL, NodeInterface $environment = NULL) {
    $form_state->set('environment', $environment);

    // @todo - Get the backups.
    $backups = \Drupal::service('ua_sm_custom.backup')->get($site->id(), $environment->id());
    $backup_times = [];
    if (isset($backups) && !is_null($backups)) {
      foreach ($backups as $backup) {
        $backup_times[$backup] = \Drupal::service('date.formatter')->format($backup);
      }
    }

    // @todo Revisit this when there's more than one platform.
    $platform_ids = \Drupal::entityQuery('node')
      ->condition('type', 'ua_sm_platform')
      ->execute();
    $platform_id = array_pop($platform_ids);

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
      'field_ua_sm_git_reference' => [
        '#type' => 'textfield',
        '#title' => $this->t('Git tag/branch'),
        '#default_value' => $environment->field_ua_sm_git_reference->value,
        '#size' => 60,
        '#maxlength' => 50,
        '#required' => TRUE,
        '#disabled' => TRUE,
      ],
      'field_ua_sm_machine_name' => [
        '#type' => 'select',
        '#title' => $this->t('Clone to environment'),
        '#options' => self::MACHINE_NAMES,
        '#default_value' => self::MACHINE_NAMES[$environment->field_ua_sm_machine_name->value],
        '#maxlength' => 255,
        '#required' => TRUE,
      ],
      'field_ua_sm_domain_name' => [
        '#type' => 'value',
        '#value' => $site->field_ua_sm_domain_name->value,
      ],
      'field_ua_sm_environment_status' => [
        '#type' => 'value',
        '#value' => '0',
      ],
      'field_ua_sm_site' => [
        '#type' => 'value',
        '#value' => $site->id(),
      ],
      'field_ua_sm_database_password' => [
        '#type' => 'value',
        '#value' => \Drupal::service('ua_sm_custom.password')->generate(),
      ],
      'field_ua_sm_platform' => [
        '#type' => 'value',
        '#value' => $platform_id,
      ],
      'type' => [
        '#type' => 'value',
        '#value' => 'ua_sm_environment',
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
    $site = Node::load($input['field_ua_sm_site']);

    // Assign a unique domain name.
    $input['field_ua_sm_domain_name'] = \Drupal::service('ua_sm_custom.hosts_config')
      ->generateDomainForEnv($site, $input['field_ua_sm_machine_name']);

    $environment = Node::create($input);
    $environment->validate();
    $environment->save();

    drupal_set_message($this->t('Successfully cloned %old_title environment to %new_title.', [
      '%old_title' => $form_state->get('environment')->getTitle(),
      '%new_title' => $input['title'],
    ]));

    $form_state->setRedirect(
      'view.ua_sm_site_environments.page_1',
      ['node' => $input['field_ua_sm_site']]
    );
  }

}
