<?php

namespace Drupal\shp_custom\Service;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

/**
 * Class Site.
 *
 * @package Drupal\shp_custom\Service
 */
class Site {

  use StringTranslationTrait;

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
    $term = Term::load($environment->field_shp_environment_type->getString());
    $site = Node::load($environment->field_shp_site->getString());
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
      'shp_distribution' => $keyedArray($site->field_shp_distribution->referencedEntities()),
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
    $results = \Drupal::entityQuery('node')
      ->condition('type', $node_type)
      ->condition($reference_field, $node->id())
      ->condition('status', NODE_PUBLISHED)
      ->execute();
    return Node::loadMultiple($results);
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
    // @todo - DREAMS
  }

}
