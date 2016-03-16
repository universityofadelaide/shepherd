<?php

/**
 * @file
 * Contains \Drupal\ua_sm_custom\Form\UserDeleteForm.
 */

namespace Drupal\ua_sm_custom\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;



/**
 * Class UserDeleteForm.
 *
 * @package Drupal\ua_sm_custom\Form
 */
class UserDeleteForm extends ConfirmFormBase {

  protected $node;
  protected $user;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ua_sm_customer_user_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL, $user = NULL) {
    $this->node = $node;
    $this->user = User::load($user);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('view.ua_sm_site_users.page_1', ['node' => $this->node->nid->value]);
  }
  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Remove');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to remove %user from %site', [
      '%user' => $this->user->field_ua_user_preferred_name->getString(),
      '%site' => $this->node->title->value,
      ]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $paragraph_entities = $this->node->field_ua_sm_users->referencedEntities();
    foreach ($paragraph_entities as $delta => $paragraph_entity) {
      if ($paragraph_entity->field_ua_sm_user->getString() == $this->user->uid->value) {
        $paragraph_storage = \Drupal::entityTypeManager()->getStorage('paragraph');
        $paragraph_storage->delete([$paragraph_entity]);
        // Stale references remain, remove the item.
        $this->node->field_ua_sm_users->removeItem($delta);
        $this->node->save();
        break;
      }
    }

    drupal_set_message($this->t('The %user has been removed from the site : %site'), [
      '%user' => $this->user->field_ua_user_preferred_name->getString(),
      '%site' => $this->node->title->value
    ]);

    $form_state->setRedirect(
      'view.ua_sm_site_users.page_1',
      ['node' => $this->node->id()]
    );

  }

}

