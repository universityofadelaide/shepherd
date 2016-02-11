<?php

/**
 * @file
 * Contains \Drupal\ua_sm_custom\Form\UserEditForm.
 */

namespace Drupal\ua_sm_custom\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;

/**
 * Class UserEditForm.
 *
 * @package Drupal\ua_sm_custom\Form
 */
class UserEditForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ua_sm_custom_user_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL, $user = NULL) {
    $form_state->set('site', $node);

    $roles = \Drupal::config('ua_sm_custom.settings')
      ->get('controlled_roles');

    $account = User::load($user);
    $form_state->set('user', $account);

    // No record of user.
    if (!$account) {
      // @todo - throw exception
      drupal_set_message($this->t('User %id not found', [
        '%id' => $user
      ]), 'error');
      return $form;
    }

    $paragraph_entities = $node->get('field_ua_sm_users')->referencedEntities();

    // Search the paragraph entities.
    $paragraph_user = $this->getParagraphUserEntity($paragraph_entities, $user);

    // User exists but not assigned to this site.
    if (!$paragraph_user) {
      // @todo - throw exception
      drupal_set_message($this->t('User %id does not belong to %site', [
        '%id' => $user,
        '%site' => $node->getTitle()
      ]), 'error');
      return $form;
    }

    // Store the paragraph entity for referencing to update.
    $form_state->set('paragraph', $paragraph_user);

    $current_role = $paragraph_user->field_ua_sm_role->getString();

    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#value' => $account->field_ua_user_preferred_name->getString(),
      '#disabled' => TRUE
    ];
    $form['ua_uid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Users employee id'),
      '#value' => $account->getAccountName(),
      '#disabled' => TRUE
    ];
    $form['email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email Address'),
      '#value' => $account->getEmail(),
      '#disabled' => TRUE
    ];
    $form['role'] = [
      '#type' => 'select',
      '#title' => $this->t('Role'),
      '#options' => $roles,
      '#default_value' => $current_role,
      '#description' => $this->t('Choose the role.'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update')
    ];

    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $input = $form_state->getUserInput();
    $role = $input['role'];

    $user = $form_state->get('user');
    $paragraph_user = $form_state->get('paragraph');
    $site = $form_state->get('site');

    $paragraph_user->field_ua_sm_role->setValue($role);
    $paragraph_user->save();
    $site->save();

    drupal_set_message($this->t('Successfully updated user %uid to site.', [
      '%uid' => $user->field_ua_user_preferred_name->getString(),
    ]));

  }

  /**
   * Searches an array of paragraph entities for a matching user.
   *
   * @param array $paragraph_entities
   *   Array of paragraph entities.
   * @param string $user_id
   *   User Id.
   *
   * @return mixed
   *   Returns the matching paragraph entity.
   */
  private function getParagraphUserEntity(array $paragraph_entities, $user_id) {
    foreach ($paragraph_entities as $paragraph_entity) {
      // Get the user_field as a string.
      $user_field = $paragraph_entity->field_ua_sm_user->getString();
      if ($user_field === $user_id) {
        return $paragraph_entity;
      }
    }
  }

}
