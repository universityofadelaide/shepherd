<?php

namespace Drupal\shp_custom\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\HttpFoundation\RequestStack;

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
    // @todo too many cross dependencies on this service, causing install failures. Fix.
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
    // @todo Set this permission to something more granular.
    $access = $this->currentUser->hasPermission('administer nodes');
    $this->setSiteField($form, $access);
    $this->setBranchField($form, $form_state);
    $this->applyJavascriptEnvironmentType($form);
    $this->replaceCronTokens($form, $form_state);
  }

  /**
   * Unless its a new environment, hide the db pre-populate field.
   *
   * @param array $form
   *   Form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public function checkHideDbPrepop(array &$form, FormStateInterface $form_state) {
    $node = $form_state->getFormObject()->getEntity();
    if (!$node->isNew()) {
      $form['field_skip_db_prepop']['#access'] = FALSE;
    }
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
    $values = $form_state->getValues();

    // If our required values are missing, clear the proposed values
    // and bail to avoid generating 500 errors.
    if (empty($values['field_shp_environment_type'])
      || empty($values['field_shp_site'])
      || empty($values['field_shp_site'][0]['target_id'])) {
      $ajax_response->addCommand(new InvokeCommand('#edit-field-shp-domain-0-value', 'val', ['']));
      $ajax_response->addCommand(new InvokeCommand('#edit-field-shp-path-0-value', 'val', ['']));
      return $ajax_response;
    }

    // Load the site and term to use their values to create the unique name.
    $site = $this->node->load($values['field_shp_site'][0]['target_id']);
    $taxonomy_term = $this->taxonomyTerm->load($values['field_shp_environment_type'][0]['target_id']);

    $path_value = $site->field_shp_path->value;
    $domain_value = $this->getUniqueDomainForSite($site, $taxonomy_term);

    // We have the unique values. W00t.
    $ajax_response->addCommand(new InvokeCommand('#edit-field-shp-domain-0-value', 'val', [$domain_value]));
    $ajax_response->addCommand(new InvokeCommand('#edit-field-shp-path-0-value', 'val', [$path_value]));

    return $ajax_response;
  }

  /**
   * Gets a unique domain value for a given site.
   *
   * @param \Drupal\node\NodeInterface $site
   *   The site node.
   * @param \Drupal\taxonomy\TermInterface $environment_type
   *   The environment type term.
   *
   * @return string
   *   A unique domain.
   */
  public function getUniqueDomainForSite(NodeInterface $site, TermInterface $environment_type) {
    // Ahh the rarely seen in the wild - do-while loop!
    $count = 0;
    do {
      $domain_value = $site->field_shp_short_name->value . '-' . $count++ . '.' . $environment_type->field_shp_base_domain->value;
    } while (!$this->validateEnvironmentNameUniqueness($domain_value));
    return $domain_value;
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
    if (!$site = $this->getSite($entity)) {
      return;
    }
    $environment_term = $this->getEnvironmentType($entity);

    // If it's not a protected environment, it can be promoted.
    if (!$environment_term->field_shp_protect->value) {
      $operations['promote'] = [
        'title'      => $this->t('Promote'),
        'weight'     => 1,
        'url'        => Url::fromRoute('shp_custom.environment-promote-form',
          ['site' => $site->id(), 'environment' => $entity->id()]),
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

    $site = $this->getSite($entity);
    if ($site) {
      $project = $this->site->getProject($site);
      if ($project) {
        if ($terminal_link = $this->getTerminalLink($entity, $site)) {
          $operations['terminal'] = $terminal_link;
        }
        if ($log_link = $this->getLogLink($entity, $site)) {
          $operations['log'] = $log_link;
        }
      }
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
   * Generate the link for the web terminal UI.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Environment entity.
   * @param \Drupal\Core\Entity\EntityInterface $site
   *   Site entity.
   *
   * @return array
   *   Renderable link.
   */
  protected function getTerminalLink(EntityInterface $entity, EntityInterface $site): array {
    $terminal = $this->orchestrationProvider->getTerminalUrl(
      $site->id(),
      $entity->id()
    );
    if ($terminal) {
      return [
        'title' => $this->t('Terminal'),
        'weight' => 9,
        'url' => $terminal,
      ];
    }
    return [];
  }

  /**
   * Generate the link for the web log UI.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Environment entity.
   * @param \Drupal\Core\Entity\EntityInterface $site
   *   Site entity.
   *
   * @return array
   *   Renderable link.
   */
  protected function getLogLink(EntityInterface $entity, EntityInterface $site): array {
    $logs = $this->orchestrationProvider->getLogUrl(
      $site->id(),
      $entity->id()
    );
    if ($logs) {
      return [
        'title' => $this->t('Logs'),
        'weight' => 4,
        'url' => $logs,
      ];
    }
    return [];
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
   * Get the link to an environment.
   *
   * @param \Drupal\node\NodeInterface $environment
   *   The environment node.
   * @param bool $render
   *   Whether to render the link or not.
   *
   * @return \Drupal\Core\Link|array|null
   *   A renderable link to the environment, null if it doesn't exist.
   */
  public function getEnvironmentLink(NodeInterface $environment, $render = TRUE) {
    $environment_term = $this->getEnvironmentType($environment);
    if (!$environment_term) {
      return NULL;
    }

    // If its a protected environment, its production, show that url.
    if ($environment_term->field_shp_protect->value) {
      $site = $environment->field_shp_site->first()->entity;
      $domain_and_path = rtrim($site->field_shp_domain->value . $site->field_shp_path->value, '/');
      $link = Link::fromTextAndUrl($domain_and_path, Url::fromUri('//' . $domain_and_path));
      return $render ? $link->toRenderable() : $link;
    }
    else {
      $domain_and_path = rtrim($environment->field_shp_domain->value . $environment->field_shp_path->value, '/');
      $link = Link::fromTextAndUrl($domain_and_path, Url::fromUri('//' . $domain_and_path));
      return $render ? $link->toRenderable() : $link;
    }
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

  /**
   * Ensure that the environment name generated is unique.
   *
   * @param string $environment_name
   *   Generated environment name.
   *
   * @return bool
   *   True if unique, false if not.
   */
  protected function validateEnvironmentNameUniqueness($environment_name) {
    $results = $this->node->getQuery()
      ->condition('type', 'shp_environment')
      ->condition('field_shp_domain', $environment_name)
      ->execute();

    return !count($results);
  }

}
