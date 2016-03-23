<?php

/**
 * @file
 * Contains \Drupal\ua_sm_custom\Form\SiteAddForm.
 */

namespace Drupal\ua_sm_custom\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;

/**
 * Class SiteAddForm.
 *
 * @package Drupal\ua_sm_custom\Form
 */
class SiteAddForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ua_sm_custom_site_add_form';
  }

  protected $fields;

  public function __construct() {
    $top_menu_style_config = FieldStorageConfig::loadByName('node', 'field_ua_sm_top_menu_style');
    $top_menu_style_settings = $top_menu_style_config->getSettings();
    $top_menu_style_options = $top_menu_style_settings['allowed_values'];

    // @todo Use a better method of getting default distribution.
    $distributionEntities = \Drupal::entityQuery('node')
      ->condition('type', 'ua_sm_distribution')
      ->execute();

    $distribution = [];
    foreach ($distributionEntities as $entity) {
      $node = Node::load($entity);
      $distribution[$entity] = $node->field_ua_sm_git_repository->value;
    }

    $this->fields = [
      'title' => [
        '#type' => 'textfield',
        '#title' =>  $this->t('Administrative name'),
        '#size' => 60,
        '#maxlength' => 255,
        '#required' => TRUE,
      ],
      'field_ua_sm_site_title' => [
        '#type' => 'textfield',
        '#title' =>  $this->t('Site title'),
        '#size' => 60,
        '#maxlength' => 255,
        '#required' => TRUE,
      ],
      'field_ua_sm_authoriser_name' => [
        '#type' => 'textfield',
        '#title' =>  $this->t('Authoriser title'),
        '#size' => 60,
        '#maxlength' => 255,
        '#required' => TRUE,
      ],
      'field_ua_sm_authoriser_email' => [
        '#type' => 'email',
        '#title' =>  $this->t('Authoriser email'),
        '#size' => 60,
        '#required' => TRUE,
      ],
      'field_ua_sm_maintainer_name' => [
        '#type' => 'textfield',
        '#title' =>  $this->t('Maintainer title'),
        '#size' => 60,
        '#maxlength' => 255,
        '#required' => TRUE,
      ],
      'field_ua_sm_maintainer_email' => [
        '#type' => 'email',
        '#title' =>  $this->t('Maintainer email'),
        '#size' => 60,
        '#required' => TRUE,
      ],
      'field_ua_sm_domain_name' => [
        '#type' => 'textfield',
        '#title' =>  $this->t('Domain'),
        '#default_value' => 'adelaide.edu.au',
        '#size' => 60,
        '#maxlength' => 255,
        '#required' => TRUE,
      ],
      'field_ua_sm_path' => [
        '#type' => 'textfield',
        '#title' =>  $this->t('Path'),
        '#size' => 60,
        '#maxlength' => 255,
      ],
      'field_ua_sm_top_menu_style' => [
        '#type' => 'select',
        '#title' => $this->t('Top menu style'),
        '#options' => $top_menu_style_options,
        '#default_value' => reset($top_menu_style_options),
      ],
      'field_ua_sm_distribution' => [
        '#type' => 'select',
        '#title' => $this->t('Distribution'),
        '#options' => $distribution,
        '#default_value' => reset($distribution),
        '#required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $build = $this->fields;
    $build['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $input = $form_state->getUserInput();

    // Prevent duplicate path domain.
    // @todo Handle slashes before/after path.
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'ua_sm_site')
      ->condition('field_ua_sm_domain_name', $input['field_ua_sm_domain_name']);
    if ($input['field_ua_sm_path']) {
      $query->condition('field_ua_sm_path', $input['field_ua_sm_path']);
    }
    $path_domain_exists = $query->execute();

    if ($path_domain_exists) {
      $form_state->setErrorByName('field_ua_sm_path', $this->t('domain with path already exists.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $input = $form_state->getUserInput();

    $site_fields = [
      'type' => 'ua_sm_site',
      'field_ua_sm_admin_password' => \Drupal::service('ua_sm_custom.password')->generate(),
    ];

    foreach (array_keys($this->fields) as $field_name) {
      $site_fields[$field_name] = $input[$field_name];
    }
    $site = Node::create($site_fields);
    $site->save();

    drupal_set_message($this->t('Successfully added site %title.', [
      '%title' => $input['title'],
    ]));

    $form_state->setRedirect(
      'view.ua_sm_site_details.page_1',
      ['node' => $site->id()]
    );
  }

}
