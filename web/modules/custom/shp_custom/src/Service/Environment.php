<?php

namespace Drupal\shp_custom\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
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
   * Environment constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *    Request stack service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *    Entity Type Manager.
   */
  public function __construct(RequestStack $requestStack, EntityTypeManagerInterface $entityTypeManager) {
    $this->requestStack = $requestStack;
    $this->entityTypeManager = $entityTypeManager;
    $this->currentRequest = $this->requestStack->getCurrentRequest();
    $this->node = $this->entityTypeManager->getStorage('node');
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

    // If the form has a site_id query param.
    if ($this->currentRequest->query->has('site_id')) {
      // Get the site id.
      $site_id = $this->currentRequest->query->get('site_id');
      $form['field_shp_site']['widget'][0]['target_id']['#default_value'] = $this->node->load($site_id);
    }

  }

}
