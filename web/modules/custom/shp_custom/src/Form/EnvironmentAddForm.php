<?php

namespace Drupal\shp_custom\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Class SiteAddForm.
 *
 * @package Drupal\shp_custom\Form
 */
class EnvironmentAddForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shp_custom_environment_add_form';
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

    $build = [
      'title' => [
        '#type' => 'textfield',
        '#title' => $this->t('Name'),
        '#size' => 60,
        '#maxlength' => 255,
        '#required' => TRUE,
      ],
      'field_shp_git_reference' => [
        '#type' => 'textfield',
        '#title' => $this->t('Git tag/branch'),
        '#size' => 60,
        '#maxlength' => 50,
        '#required' => TRUE,
      ],
      'field_shp_machine_name' => [
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
      'field_shp_domain_name' => [
        '#type' => 'value',
        '#value' => $node->field_shp_domain->value,
      ],
      'field_shp_site' => [
        '#type' => 'value',
        '#value' => $node->id(),
      ],
      'type' => [
        '#type' => 'value',
        '#value' => 'shp_environment',
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
    $site = Node::load($input['field_shp_site']);

    // Generate unique domain for environment.
    $input['field_shp_domain_name'] = \Drupal::service('shp_custom.hosts_config')
      ->generateDomainForEnv($site, $input['field_shp_machine_name']);

    $environment = Node::create($input);
    $environment->validate();
    // @todo We should validate this to ensure only one prd environment exists.
    $environment->save();

    drupal_set_message($this->t('Successfully added environment %title.', [
      '%title' => $input['title'],
    ]));

  }

}
