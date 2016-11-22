<?php

namespace Drupal\ua_sm_custom\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Class SiteAddForm.
 *
 * @package Drupal\ua_sm_custom\Form
 */
class EnvironmentAddForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ua_sm_custom_environment_add_form';
  }

  /**
   * Callback to get page title for the name of the site.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Site node.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Translated markup.
   */
  public function getPageTitle(NodeInterface $node) {
    return t('Create new environment for @site_title', ['@site_title' => $node->getTitle()]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {

    $platform = ua_sm_custom_choices('ua_sm_platform');

    $build = [
      'title' => [
        '#type' => 'textfield',
        '#title' => $this->t('Name'),
        '#size' => 60,
        '#maxlength' => 255,
        '#required' => TRUE,
      ],
      'field_ua_sm_git_reference' => [
        '#type' => 'textfield',
        '#title' => $this->t('Git tag/branch'),
        '#size' => 60,
        '#maxlength' => 50,
        '#required' => TRUE,
      ],
      'field_ua_sm_machine_name' => [
        '#type' => 'select',
        '#title' => $this->t('Type'),
        '#options' => [
          'dev' => 'DEV',
          'uat' => 'UAT',
          'prd' => 'PRD',
        ],
        '#default_value' => 'dev',
        '#maxlength' => 255,
        '#required' => TRUE,
      ],
      'field_ua_sm_domain_name' => [
        '#type' => 'value',
        '#value' => $node->field_ua_sm_domain_name->value,
      ],
      'field_ua_sm_site' => [
        '#type' => 'value',
        '#value' => $node->id(),
      ],
      'field_ua_sm_database_password' => [
        '#type' => 'value',
        '#value' => \Drupal::service('ua_sm_custom.password')->generate(),
      ],
      'field_ua_sm_platform' => [
        '#type' => 'select',
        '#title' => $this->t('Platform'),
        '#options' => $platform,
        '#default_value' => reset($platform),
        '#required' => TRUE,
        '#states' => [
          'invisible' => [
            ':input[name="field_ua_sm_create_site"]' => ['checked' => FALSE],
          ],
        ],
      ],
      'type' => [
        '#type' => 'value',
        '#value' => 'ua_sm_environment',
      ],
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Add'),
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

    // Load parent site to get its domain.
    $site = Node::load($input['field_ua_sm_site']);

    // Generate unique domain for environment.
    $input['field_ua_sm_domain_name'] = \Drupal::service('ua_sm_custom.hosts_config')
      ->generateDomainForEnv($site, $input['field_ua_sm_machine_name']);

    $environment = Node::create($input);
    $environment->validate();
    // @todo We should validate this to ensure only one prd environment exists.
    $environment->save();

    drupal_set_message($this->t('Successfully added environment %title.', [
      '%title' => $input['title'],
    ]));

    $form_state->setRedirect(
      'view.ua_sm_site_environments.page_1',
      ['node' => $input['field_ua_sm_site']]
    );
  }

}
