<?php

/**
 * @file
 * Contains \Drupal\ua_sm_custom\Form\UserAddForm.
 */

namespace Drupal\ua_sm_custom\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;

/**
 * Class UserAddForm.
 *
 * @package Drupal\ua_sm_custom\Form
 */
class UserAddForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ua_sm_custom_user_add_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $form_state->set('site', $node);

    // @TODO: pull roles from config.
    $roles = [
      'administrator' => 'Administrator',
      'ua_editor' => 'Editor',
      'ua_author' => 'Author',
    ];

    $build['uid'] = [
      '#type' => 'textfield',
      '#title' => t('User ID'),
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,
    ];
    $build['role'] = [
      '#type' => 'select',
      '#title' => $this->t('Role'),
      '#options' => $roles,
      '#default_value' => $roles[0],
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
    $input = $form_state->getUserInput();
    $uid = $input['uid'];
    $role = $input['role'];

    // If not a drupal user then check that the user is in LDAP.
    $account = user_load_by_name($uid);
    if (!$account) {
      $attributes = \Drupal::service('ua_ldap.ldap_user')->getAttributes($uid);
      if (!$attributes) {
        $form_state->setErrorByName('uid', $this->t('Unable to find user id ' . $uid . '.'));
      }
    }
    else {
      $site = $form_state->get('site');
      if (\Drupal::service('ua_sm_custom.site')->userRoleExists($site, $account, $role)) {
        $form_state->setErrorByName('uid', $this->t('User role already exists for ' . $uid . '.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $input = $form_state->getUserInput();
    $uid = $input['uid'];
    $role = $input['role'];

    // If user not a drupal user yet then make them one.
    $account = user_load_by_name($uid);
    if (!$account) {
      $account = \Drupal::service('ua_sm_custom.user')->provision($uid);
    }

    // Create user role paragraph entity.
    $user_role_storage = \Drupal::entityTypeManager()->getStorage('paragraph');
    $user_role = $user_role_storage->create([
      'type' => 'ua_sm_user_role',
      'field_ua_sm_user' => $account,
      'field_ua_sm_role' => $role,
    ]);
    $user_role->save();

    // Add user role to site.
    $site = $form_state->get('site');
    $user_roles = $site->get('field_ua_sm_users');
    $user_roles->appendItem($user_role);
    $site->save();

    drupal_set_message($this->t('Successfully added user %uid to site.', [
      '%uid' => $uid,
    ]));
  }

}
