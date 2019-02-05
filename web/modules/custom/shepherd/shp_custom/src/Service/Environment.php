<?php

namespace Drupal\shp_custom\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\HttpFoundation\RequestStack;
use TheSeer\Tokenizer\Token;

/**
 * Environment service. Provides methods to handle Environment entities.
 *
 * @package Drupal\shp_custom\Service
 */
class Environment {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * Request service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
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
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
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
   * Site service.
   *
   * @var \Drupal\shp_custom\Service\Site
   */
  private $site;

  /**
   * Orchestration provider plugin manager.
   *
   * @var \Drupal\shp_orchestration\OrchestrationProviderPluginManagerInterface
   */
  protected $orchestrationProvider;

  /**
   * Environment constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type Manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   Current Drupal user.
   * @param \Drupal\shp_custom\Service\Site $site
   *   Site service.
   */
  public function __construct(RequestStack $requestStack,
                              EntityTypeManagerInterface $entityTypeManager,
                              AccountProxyInterface $currentUser,
                              Site $site) {
    $this->requestStack = $requestStack;
    $this->entityTypeManager = $entityTypeManager;
    $this->currentRequest = $this->requestStack->getCurrentRequest();
    $this->node = $this->entityTypeManager->getStorage('node');
    $this->taxonomyTerm = $this->entityTypeManager->getStorage('taxonomy_term');
    $this->currentUser = $currentUser;
    $this->site = $site;
    // @todo - too many cross dependencies on this service, causing install failures. Fix.
    // Pull statically for now.
    $this->orchestrationProvider = \Drupal::service('plugin.manager.orchestration_provider')->getProviderInstance();
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
    $this->setBranchField($form, $form_state);
    $this->applyJavascriptEnvironmentType($form);
    $this->replaceCronTokens($form, $form_state);
  }

  /**
   * Set the default git branch from the project.
   *
   * @param array $form
   *   Form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public function setBranchField(array &$form, FormStateInterface $form_state) {
    if ($site = $form['field_shp_site']['widget'][0]['target_id']['#default_value']) {
      $project = $this->site->getProject($site);
      $form['field_shp_git_reference']['widget'][0]['value']['#default_value'] = $project->field_shp_git_default_ref->value;
    }
  }

  /**
   * Set site field autocomplete with the site_id entity as the default value.
   *
   * @param array $form
   *   Form render array.
   * @param bool $access
   *   Current user has access to this field.
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
      'callback' => 'shp_custom_set_domain_path',
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
  public function setDomainPath(array &$form, FormStateInterface $form_state) {
    $ajax_response = new AjaxResponse();
    $taxonomy_term_id = $form_state->getValue('field_shp_environment_type')[0]['target_id'];
    $site_id = $form_state->getValue('field_shp_site')[0]['target_id'];

    if ($taxonomy_term_id) {
      $taxonomy_term = $this->loadTaxonomyTerm($taxonomy_term_id);
      $site = $this->node->load($site_id);
      $path_value = $site->field_shp_path->value;
      $domain_value = $site->field_shp_short_name->value . '.' . $taxonomy_term->field_shp_base_domain->value;
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

  /**
   * Apply alterations to entity operations.
   *
   * @param array $operations
   *   List of operations.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to apply operations to.
   */
  public function operationsLinksAlter(array &$operations, EntityInterface $entity) {
    $site = $entity->field_shp_site->target_id;
    $environment = $entity->id();
    $environment_term = Term::load($entity->field_shp_environment_type->target_id);

    // If its not a protected environment, it can be promoted.
    if (!$environment_term->field_shp_protect->value) {
      $operations['promote'] = [
        'title'      => $this->t('Promote'),
        'weight'     => 1,
        'url'        => Url::fromRoute('shp_custom.environment-promote-form',
          ['site' => $site, 'environment' => $environment]),
        // Render form in a modal window.
        'attributes' => [
          'class'               => ['button', 'use-ajax'],
          'data-dialog-type'    => 'modal',
          'data-dialog-options' => Json::encode([
            'width'  => '50%',
            'height' => '50%',
          ]),
        ],
      ];
    }

    $site = $entity->field_shp_site->entity;
    $project = $site->field_shp_project->entity;

    $terminal = $this->orchestrationProvider->getTerminalUrl(
      $project->getTitle(),
      $site->field_shp_short_name->value,
      $entity->id()
    );

    $logs = $this->orchestrationProvider->getLogUrl(
      $project->getTitle(),
      $site->field_shp_short_name->value,
      $entity->id()
    );

    if ($terminal) {
      $operations['terminal'] = [
        'title'      => $this->t('Terminal'),
        'weight'     => 9,
        'url'        => $terminal,
      ];
    }

    if ($logs) {
      $operations['logs'] = [
        'title'     => $this->t('Logs'),
        'weight'    => 4,
        'url'       => $logs,
      ];
    }

    // Process copied from getDefaultOperations()
    $destination = $this->currentRequest->getRequestUri();
    foreach ($operations as $key => $operation) {
      if (!isset($operations[$key]['query'])) {
        $operations[$key]['query'] = ['destination' => $destination];
      }
    }
  }

  /**
   * Retrieve the related Site of an Environment entity.
   *
   * @param \Drupal\node\NodeInterface $environment
   *   Environment entity.
   *
   * @return \Drupal\node\NodeInterface|bool
   *   The site entity or FALSE.
   */
  public function getSite(NodeInterface $environment) {
    if (isset($environment->field_shp_site->target_id)) {
      return $environment->field_shp_site->entity;
    }

    return FALSE;
  }

  /**
   * Retrieve the related Environment type.
   *
   * @param \Drupal\node\NodeInterface $environment
   *   Environment entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|bool
   *   The environment type term or FALSE.
   */
  public function getEnvironmentType(NodeInterface $environment) {
    if (!$environment->field_shp_environment_type->isEmpty()) {
      return $environment->field_shp_environment_type->entity;
    }

    return FALSE;
  }

  /**
   * Replace any tokens from default field values.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public function replaceCronTokens(array &$form, FormStateInterface $form_state) {
    foreach (Element::children($form['field_shp_cron_jobs']['widget']) as $element) {
      if (is_numeric($element)) {
        $form['field_shp_cron_jobs']['widget'][$element]['key']['#default_value'] = \Drupal::token()
          ->replace($form['field_shp_cron_jobs']['widget'][$element]['key']['#default_value']);
      }
    }
  }

}
