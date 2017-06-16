<?php

namespace Drupal\shp_custom\Form;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;

/**
 * Class UserAddForm.
 *
 * @package Drupal\shp_custom\Form
 */
class UserAddForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shp_custom_user_add_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $form_state->set('site', $node);

    // @todo Provide restricted list of roles which are available in the site.
    $roles = array_map('\Drupal\Component\Utility\Html::escape', user_role_names());

    // Blatantly stolen from Drupal\Core\Entity\Element\EntityAutocomplete:L138.
    $target_type = 'user';
    $selection_handler = 'default';
    $selection_settings = ['match_operator' => 'CONTAINS'];
    $data = serialize($selection_settings) . $target_type . $selection_handler;
    $selection_settings_key = Crypt::hmacBase64($data, Settings::getHashSalt());

    $key_value_storage = \Drupal::keyValue('entity_autocomplete');
    if (!$key_value_storage->has($selection_settings_key)) {
      $key_value_storage->set($selection_settings_key, $selection_settings);
    }

    $build['uid'] = [
      '#type' => 'textfield',
      '#title' => t('User'),
      '#autocomplete_route_name' => 'system.entity_autocomplete',
      '#autocomplete_route_parameters' => [
        'target_type' => $target_type,
        'selection_handler' => $selection_handler,
        'selection_settings_key' => $selection_settings_key,
      ],
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,
    ];
    $build['role'] = [
      '#type' => 'select',
      '#title' => $this->t('Role'),
      '#options' => $roles,
      '#default_value' => reset($roles),
      '#description' => $this->t('Choose the role.'),
    ];
    $build['submit'] = [
      '#type' => 'submit',
      '#value' => t('Add'),
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $input = $form_state->getValues();
    $uid = $input['uid'];
    $role = $input['role'];

    if (!$form_state->getValue('account')) {
      $match   = EntityAutocomplete::extractEntityIdFromAutocompleteInput($uid);
      $account = User::load($match);
    }
    else {
      $account = $form_state->getValue('account');
    }

    if (!$account) {
      $form_state->setErrorByName('uid', $this->t('Unable to find user id %uid.', ['%uid' => $uid]));
    }
    else {
      $site = $form_state->get('site');
      if (\Drupal::service('shp_custom.site')->userRoleExists($site, $account, $role)) {
        $form_state->setErrorByName('uid', $this->t('User role already exists for %uid.', ['%uid' => $uid]));
      }
      else {
        $form_state->setValue('account', $account);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $role = $form_state->getValue('role');

    // If user not a drupal user yet then make them one.
    $account = $form_state->getValue('account');

    // Create user role paragraph entity.
    $user_role_storage = \Drupal::entityTypeManager()->getStorage('paragraph');
    $user_role = $user_role_storage->create([
      'type' => 'shp_user_role',
      'field_shp_user' => $account,
      'field_shp_role' => $role,
    ]);
    $user_role->save();

    // Add user role to site.
    $site = $form_state->get('site');
    $user_roles = $site->get('field_shp_users');
    $user_roles->appendItem($user_role);
    $site->save();

    drupal_set_message($this->t('Successfully added user %uid to site.', [
      '%uid' => $account->name->value,
    ]));

    $form_state->setRedirect(
      'view.shp_site_users.page_1',
      ['node' => $site->id()]
    );
  }

}
