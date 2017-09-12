<?php

/**
 * @file
 * Enables modules and site configuration for a Shepherd site installation.
 */

use Drupal\contact\Entity\ContactForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_FORM_ID_alter() for install_configure_form().
 *
 * Allows the profile to alter the site configuration form.
 */
function shepherd_form_install_configure_form_alter(&$form, FormStateInterface $form_state) {
  // Pre-populate the site name with the server name.
  $form['site_information']['site_name']['#default_value'] = 'Shepherd';
  $form['#submit'][] = 'shepherd_form_install_configure_submit';
}

/**
 * Submission handler to sync the contact.form.feedback recipient.
 */
function shepherd_form_install_configure_submit($form, FormStateInterface $form_state) {
  $site_mail = $form_state->getValue('site_mail');
  ContactForm::load('feedback')->setRecipients([$site_mail])->save();
}
