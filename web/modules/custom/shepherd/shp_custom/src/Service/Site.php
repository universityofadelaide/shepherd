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
use Drupal\node\NodeInterface;

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
  public function setGoLiveDate(Node $environment) {
    $term = $this->taxonomyTerm->load($environment->field_shp_environment_type->target_id);
    $site = $this->node->load($environment->field_shp_site->getString());
    if ($term->field_shp_update_go_live->value) {
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
      'shp_project' => $keyedArray($site->field_shp_project->referencedEntities()),
    ];

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
   *   Node type.
   * @param string $field
   *   Field name.
   * @param string $field_value
   *   The fields value.
   *
   * @return mixed
   *   Query results.
   */
  public function loadEntitiesByFieldValue($node_type, $field, $field_value) {
    $results = $this->node->getQuery()
      ->condition('type', $node_type)
      ->condition($field, $field_value)
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
  }

  /**
   * Apply #ajax to the title field.
   *
   * @param array $form
   *   Form render array.
   */
  public function applyJavascriptTitleField(array &$form) {
    // #machine_name needs an id to attach to.
    $form['title']['widget'][0]['value']['#id'] = 'edit-title';
    $form['field_shp_short_name']['widget'][0]['value']['#type'] = 'machine_name';
    $form['field_shp_short_name']['widget'][0]['value']['#machine_name'] = [
      'exists' => ['shp_custom_generate_unique_short_name'],
      'source' => ['title', 'widget', '0', 'value'],
      'replace_pattern' => '[^a-z0-9-]+',
      'replace' => '-',
    ];
  }

  /**
   * Ensure that the short_name generated is unique.
   *
   * @param string $short_name
   *   Generated short name.
   *
   * @return bool
   *   True if unique, false if not.
   */
  public function validateShortNameUniqueness($short_name) {
    $results = $this->loadEntitiesByFieldValue('shp_site', 'field_shp_short_name', $short_name);
    return !count($results);
  }

  /**
   * Loads the project related to a site.
   *
   * @param \Drupal\node\NodeInterface $site
   *   Site node.
   *
   * @return \Drupal\node\NodeInterface|bool
   *   Project node.
   */
  public function getProject(NodeInterface $site) {
    if (isset($site->field_shp_project->target_id)) {
      /** @var \Drupal\node\NodeInterface $project */
      return $site->get('field_shp_project')
        ->first()
        ->get('entity')
        ->getTarget()
        ->getValue();
    }

    return FALSE;
  }

}
