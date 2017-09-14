<?php

namespace Drupal\shp_custom\Service;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\Node;

/**
 * Class Site.
 *
 * @package Drupal\shp_custom\Service
 */
class Site {

  use StringTranslationTrait;
  use DependencySerializationTrait;

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
   * Site constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type Manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->taxonomyTerm = $this->entityTypeManager->getStorage('taxonomy_term');
    $this->node = $this->entityTypeManager->getStorage('node');
  }

  /**
   * Takes an environment entity and applies a sites go live date.
   *
   * @param \Drupal\node\Entity\Node $environment
   *   The environment node entity.
   *
   * @return bool
   *   TRUE if applied go live date.
   */
  public function checkGoLiveApplied(Node $environment) {
    $term = $this->taxonomyTerm->load($environment->field_shp_environment_type->getString());
    $site = $this->node->load($environment->field_shp_site->getString());
    if ($term->getName() === "Production") {
      if (!isset($site->field_shp_go_live_date->value)) {
        $date = new DrupalDateTime();
        $site->field_shp_go_live_date->setValue($date->format(DATETIME_DATETIME_STORAGE_FORMAT));
        $site->save();
        drupal_set_message($this->t('Site %name go live date applied.', [
          '%name' => $site->getTitle(),
        ]));
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Load all entities related to a site.
   *
   * @param \Drupal\node\Entity\Node $site
   *   The site node.
   *
   * @return array
   *   An array of related entities keyed by type.
   */
  public function loadRelatedEntities(Node $site) {
    // referencedEntities() doesn't key by node id; This re-keys by node id.
    $keyedArray = function ($nodes) {
      $keyed_array = [];
      foreach ($nodes as $node) {
        $keyed_array[$node->id()] = $node;
      }
      return $keyed_array;
    };

    $nodes = [
      'shp_site' => [$site->id() => $site],
      'shp_environment' => $this->loadRelatedEntitiesByField($site, 'field_shp_site', 'shp_environment'),
      'shp_distribution' => $keyedArray($site->field_shp_project->referencedEntities()),
    ];

    foreach ($nodes['shp_environment'] as $environment) {
      // @todo Shepherd: Platforms are gone, what to do here?
    }

    return $nodes;
  }

  /**
   * Reverse loading of related entities for a specific field and node type.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The referenced node.
   * @param string $reference_field
   *   The entity reference field name.
   * @param string $node_type
   *   The node type.
   *
   * @return array
   *   An array of nodes
   */
  public function loadRelatedEntitiesByField(Node $node, $reference_field, $node_type) {
    $results = $this->node->getQuery()
      ->condition('type', $node_type)
      ->condition($reference_field, $node->id())
      ->condition('status', NODE_PUBLISHED)
      ->execute();
    return $this->node->loadMultiple($results);
  }

  /**
   * Load all entities that contain a value in a field.
   *
   * @param string $node_type
   *   Node type. Defaults to shp_site.
   * @param string $field
   *   Field name.
   * @param string $field_value
   *   The fields value.
   *
   * @return mixed
   *   Query results.
   */
  public function loadEntitiesByFieldValue($node_type = 'shp_site', $field, $field_value) {
    $results = $this->node->getQuery()
      ->condition('type', $node_type)
      ->condition($field, $field_value, 'CONTAINS')
      ->execute();
    return $results;
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
    $this->applyJavascriptTitleField($form);
    // First make the short_name only visible after text has appeared in it.
    $this->applyJavascriptShortNameField($form);
    // Add javascript that triggers a delayed event after input finished.
    $form['#attached']['library'] = [
      'shp_custom/input_event_delay',
    ];
  }

  /**
   * Apply #ajax to the title field.
   *
   * @param array $form
   *   Form render array.
   */
  public function applyJavascriptTitleField(array &$form) {
    $form['title']['widget'][0]['value']['#ajax'] = [
      'callback' => [$this, 'setShortNameAjax'],
      'event' => 'inputdelay',
      'progress' => [
        'type' => 'throbber',
        'message' => 'Creating short name',
      ],
    ];
  }

  /**
   * Ajax callback that creates the short_name from the title input.
   *
   * @param array $form
   *   Form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Returns ajax response object with commands to update field ui.
   */
  public function setShortNameAjax(array &$form, FormStateInterface $form_state) {
    $ajax_response = new AjaxResponse();
    $title_value = $form_state->getValue('title')[0]['value'];
    // Generate and validate the short name.
    $short_name = $this->validateShortNameUniqueness($this->createShortName($title_value));
    $form_state->setValue('field_shp_short_name', [
      ['value' => $short_name],
    ]);
    $form['field_shp_short_name']['widget'][0]['value']['#value'] = $short_name;
    $form_state->setValidationComplete(FALSE);
    $form_state->setRebuild(TRUE);
    // Rebuild the form and get the validation errors.
    $form_state->getFormObject()->validateForm($form, $form_state);
    // The form state should be modified that we can then get errors.
    $errors = $form_state->getErrors();
    if ($errors) {
      $fields = ['field_shp_short_name][0][value', 'field_shp_short_name][0'];
      foreach ($errors as $field => $error) {
        if (in_array($field, $fields)) {
          // Convert into a string.
          // Extract the one we care about.
          $short_name_error = $error->render();
          $ajax_response->addCommand(new HtmlCommand('#field-shp-short-name-ajax-response', $short_name_error));
          $ajax_response->addCommand(new InvokeCommand('#field-shp-short-name-ajax-response', 'css', ['color', 'red']));
        }
      }
      // Flush the errors.
      $form_state->clearErrors();
    }
    $ajax_response->addCommand(new InvokeCommand('#edit-field-shp-short-name-0-value', 'val', [$short_name]));

    return $ajax_response;
  }

  /**
   * Create a valid short name from the title field.
   *
   * @param string $title
   *   Value from the title field.
   *
   * @return string
   *   A valid short_name.
   */
  public function createShortName($title) {
    return preg_replace('/[^a-z0-9-]/', '-', trim(strtolower($title)));
  }

  /**
   * Apply #states and #ajax to the shp_short_name field.
   *
   * @param array $form
   *   Form render array.
   */
  public function applyJavascriptShortNameField(array &$form) {
    $form['field_shp_short_name']['#states'] = [
      // Hide the field until the title field has data.
      'invisible' => [
        ':input[name="title[0][value]"]' => ['empty' => TRUE],
      ],
    ];
    $form['field_shp_short_name']['widget'][0]['value']['#prefix'] = '<div id="field-shp-short-name-ajax-response"></div>';
  }

  /**
   * Ensure that the short_name generated is unique.
   *
   * @param string $short_name
   *    Generated short name.
   *
   * @return string
   *   If not unique adds a number to end of string, otherwise valid.
   */
  protected function validateShortNameUniqueness($short_name) {
    $results = $this->loadEntitiesByFieldValue('shp_site', 'field_shp_short_name', $short_name);
    if ($results) {
      $count = count($results);
      return $short_name . '-' . $count;
    }
    return $short_name;
  }

}
