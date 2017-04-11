<?php

/**
 * @file
 * Contains \Drupal\shp_custom\Form\SiteAddForm.
 */

namespace Drupal\shp_custom\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;

/**
 * Class SiteAddForm.
 *
 * @package Drupal\shp_custom\Form
 */
class SiteAddForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shp_custom_site_add_form';
  }

  protected $fields;

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    // TODO - remove
//    $top_menu_style_config = FieldStorageConfig::loadByName('node', 'field_shp_top_menu_style');
//    $top_menu_style_settings = $top_menu_style_config->getSettings();
//    $top_menu_style_options = $top_menu_style_settings['allowed_values'];

    // TODO - refactor module code.
    $distributions = shp_custom_distributions();
    // $platforms = shp_custom_platforms();

    $this->fields = [
      'title' => [
        '#type' => 'textfield',
        '#title' => $this->t('Administrative name'),
        '#size' => 60,
        '#maxlength' => 255,
        '#required' => TRUE,
      ],
      'field_shp_site_title' => [
        '#type' => 'textfield',
        '#title' => $this->t('Site title'),
        '#size' => 60,
        '#maxlength' => 255,
        '#required' => TRUE,
      ],
      /* TO BE DELETED.
      'field_shp_authoriser_name' => [
        '#type' => 'textfield',
        '#title' => $this->t('Authoriser title'),
        '#size' => 60,
        '#maxlength' => 255,
        '#required' => TRUE,
      ],
      'field_shp_authoriser_email' => [
        '#type' => 'email',
        '#title' =>  $this->t('Authoriser email'),
        '#size' => 60,
        '#required' => TRUE,
      ],
      'field_shp_maintainer_name' => [
        '#type' => 'textfield',
        '#title' => $this->t('Maintainer title'),
        '#size' => 60,
        '#maxlength' => 255,
        '#required' => TRUE,
      ],
      'field_shp_maintainer_email' => [
        '#type' => 'email',
        '#title' => $this->t('Maintainer email'),
        '#size' => 60,
        '#required' => TRUE,
      ],*/
      /* MOVE TO ENVIRONMENT
       * 'field_shp_domain_name' => [
        '#type' => 'textfield',
        '#title' => $this->t('Domain'),
        '#default_value' => 'adelaide.edu.au',
        '#size' => 60,
        '#maxlength' => 255,
        '#required' => TRUE,
      ],
      'field_shp_path' => [
        '#type' => 'textfield',
        '#title' => $this->t('Path'),
        '#size' => 60,
        '#maxlength' => 255,
      ],*/
      /* TO BE DELETED - ADD TO INSTALL PROFILE OPTIONS.
      'field_shp_top_menu_style' => [
        '#type' => 'select',
        '#title' => $this->t('Top menu style'),
        '#options' => $top_menu_style_options,
        '#default_value' => reset($top_menu_style_options),
      ],*/
      'field_shp_distribution' => [
        '#type' => 'select',
        '#title' => $this->t('Distribution'),
        '#options' => $distributions,
        '#default_value' => reset($distributions),
        '#required' => TRUE,
      ],
      'field_shp_create_site' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Create default staging environment'),
        '#default_value' => TRUE,
      ],
      /* TO BE REMOVED.
       * 'field_shp_git_reference' => [
        '#type' => 'textfield',
        '#title' => $this->t('Git tag/branch'),
        '#size' => 60,
        '#maxlength' => 255,
        '#default_value' => 'master',
        '#states' => [
          'invisible' => [
            ':input[name="field_shp_create_site"]' => ['checked' => FALSE],
          ],
        ],
      ],
      'field_shp_platform' => [
        '#type' => 'select',
        '#title' => $this->t('Platform'),
        '#options' => $platforms,
        '#default_value' => reset($platforms),
        '#required' => TRUE,
        '#states' => [
          'invisible' => [
            ':input[name="field_shp_create_site"]' => ['checked' => FALSE],
          ],
        ],
      ],*/
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $build = $this->fields;
    $build['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create'),
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $input = $form_state->getValues();
    // TODO - refactor this.
    // Make sure we are using the correct slashes in the site path.
//    if (!empty($input['field_shp_path']))
//    {
//      $form_state->setValue('field_shp_path', '/' . trim($form_state->getValue('field_shp_path'), '/'));
//    }

    // Prevent duplicate path domain.
//    $query = \Drupal::entityQuery('node')
//      ->condition('type', 'shp_site')
//      ->condition('field_shp_domain_name', $input['field_shp_domain_name']);
//    if ($input['field_shp_path']) {
//      $query->condition('field_shp_path', $input['field_shp_path']);
//    }
//    $path_domain_exists = $query->execute();
//
//    if ($path_domain_exists) {
//      $form_state->setErrorByName('field_shp_path', $this->t('domain with path already exists.'));
//    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $input = $form_state->getValues();

    // TODO - remove admin password generation.
    $site_fields = [
      'type' => 'shp_site',
    //  'field_shp_admin_password' => \Drupal::service('shp_custom.password')->generate(),
    ];

    foreach (array_keys($this->fields) as $field_name) {
      $site_fields[$field_name] = $input[$field_name];
    }
    $site = Node::create($site_fields);
    $site->validate();
    $site->save();

    drupal_set_message($this->t('Successfully added site %title.', [
      '%title' => $input['title'],
    ]));

    // Don't redirect like this.
    $form_state->setRedirect('view');
    /*$form_state->setRedirect(
      'view.shp_site_details.page_1',
      ['node' => $site->id()]
    );*/
  }

}
