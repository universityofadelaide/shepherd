<?php

namespace Drupal\shp_custom\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Environment service. Provides methods to handle Environment entities.
 *
 * @package Drupal\shp_custom\Service
 */
class Environment {

  /**
   * Request service.
   *
   * @var RequestStack
   */
  protected $requestStack;

  /**
   * Current request.
   *
   * @var null|\Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * Entity Type Manager.
   *
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;


  /**
   * Node entity type.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $node;

  /**
   * Current User.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Environment constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *    Request stack service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *    Entity Type Manager.
   */
  public function __construct(RequestStack $requestStack, EntityTypeManagerInterface $entityTypeManager, AccountProxyInterface $currentUser) {
    $this->requestStack = $requestStack;
    $this->entityTypeManager = $entityTypeManager;
    $this->currentRequest = $this->requestStack->getCurrentRequest();
    $this->node = $this->entityTypeManager->getStorage('node');
    $this->currentUser = $currentUser;
  }

  /**
   * Apply alterations to node add form.
   *
   * @param array $form
   *   Form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form State.
   */
  public function formAlter(array &$form, FormStateInterface $form_state) {

    // @todo - Set this permission to something more granular.
    $access = $this->currentUser->hasPermission('administer nodes');
    $this->setSiteField($form, $access);

  }

  /**
   * Set site field autocomplete with the site_id entity as the default value.
   *
   * @param array $form
   *    Form render array.
   * @param bool $access
   *    Current user has access to this field.
   */
  public function setSiteField(array &$form, bool $access) {

    // Set the visibility of the field.
    $form['field_shp_site']['#access'] = $access;

    // If the form has a site_id query param.
    if ($this->currentRequest->query->has('site_id')) {
      // Get the site id.
      $site_id = $this->currentRequest->query->get('site_id');
      $form['field_shp_site']['widget'][0]['target_id']['#default_value'] = $this->node->load($site_id);
    }
  }

}
