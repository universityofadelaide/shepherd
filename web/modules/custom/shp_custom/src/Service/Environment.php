<?php

namespace Drupal\shp_custom\Service;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
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

  use DependencySerializationTrait;

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
   * Taxonomy term entity type.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $taxonomyTerm;

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
    $this->taxonomyTerm = $this->entityTypeManager->getStorage('taxonomy_term');
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
    $this->applyJavascriptEnvironmentType($form);
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

    // Fetch the site node if the site_id route parameter is set.
    $site_node = $this->currentRequest->get('site_id');

    // Prefill the form with the specified site.
    if (isset($site_node)) {
      $form['field_shp_site']['widget'][0]['target_id']['#default_value'] = $site_node;
    }
  }

  /**
   * Apply #ajax callbacks to environment_type that updates domain and path.
   *
   * @param array $form
   *   Form render array.
   */
  public function applyJavascriptEnvironmentType(array &$form) {
    $form['field_shp_environment_type']['widget']['#ajax'] = [
      'callback' => [$this, 'setDomainPath'],
      'event' => 'change',
      'progress' => [
        'type' => 'throbber',
        'message' => 'Retrieving environment type information.',
      ],
    ];
    $form['field_shp_environment_type']['widget']['#suffix'] = '<div id="shp_environment_type_ajax_response"></div>';
  }

  /**
   * Ajax callback that retrieves taxonomy and sets domain and path values.
   *
   * @param array $form
   *   Form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Returns ajax response with commands.
   */
  public function setDomainPath(&$form, FormStateInterface $form_state) {
    $ajax_response = new AjaxResponse();
    $taxonomy_term_id = $form_state->getValue('field_shp_environment_type')[0]['target_id'];
    $site_id = $form_state->getValue('field_shp_site')[0]['target_id'];

    if ($taxonomy_term_id) {
      $taxonomy_term = $this->loadTaxonomyTerm($taxonomy_term_id);
      $site = $this->node->load($site_id);
      $path_value = $site->field_shp_path->value;
      if (strtolower($taxonomy_term->getName()) === "production") {
        $domain_value = $site->field_shp_domain->value;
      }
      else {
        // Non production environment use the domain provided.
        $domain_value = $site->field_shp_short_name->value . '.' . $taxonomy_term->field_shp_base_domain->value;
      }
      $ajax_response->addCommand(new InvokeCommand('#edit-field-shp-domain-0-value', 'val', [$domain_value]));
      $ajax_response->addCommand(new InvokeCommand('#edit-field-shp-path-0-value', 'val', [$path_value]));
    }
    return $ajax_response;
  }

  /**
   * Loads taxonomy terms.
   *
   * @param string $tid
   *   Term id.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Taxonomy term entity.
   */
  protected function loadTaxonomyTerm($tid) {
    return $this->taxonomyTerm->load($tid);
  }

}
