<?php

namespace Drupal\shp_vue_view\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Style plugin to render a table page display with vue js.
 *
 * @ingroup view_style_plugins
 *
 * @ViewsStyle(
 *   id = "vuetable",
 *   title = @Translation("Vue JS Table"),
 *   help = @Translation("Render a table of data with vue js"),
 *   theme = "views_view_vue_table",
 *   display_types = { "normal" }
 * )
 */
class VueTable extends StylePluginBase {

  /**
   * Does the style plugin for itself support to add fields to it's output.
   *
   * @var bool
   */
  protected $usesFields = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['update_cycle'] = ['default' => 5000];
    $options['field_options'] = ['default' => []];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $fields = $this->displayHandler->getHandlers('field');
    $field_names = $this->displayHandler->getFieldLabels();
    if (empty($fields)) {
      $form['error_markup'] = [
        '#markup' => '<div class="messages messages--error">' . $this->t('You need at least one field before you can configure your table settings') . '</div>',
      ];
      return;
    }

    $form['field_description'] = [
      '#markup' => '<div class="js-form-item form-item description">' . $this->t('Associate api endpoints to fields to dynamically update.') . '</div>',
    ];

    $form['field_options'] = [
      '#type' => 'table',
      '#header' => ['Name', 'Url endpoint', 'Javascript property', 'Make active'],
    ];

    // Loop over all of the fields.
    foreach ($fields as $field => $column) {
      $form['field_options'][$field]['name'] = [
        '#type' => 'item',
        '#markup' => $this->t((string) $field_names[$field]),
      ];
      $form['field_options'][$field]['url_endpoint'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Url endpoint'),
        '#title_display' => 'invisible',
        '#placeholder' => 'Api endpoint to update',
        '#default_value' => isset($this->options['field_options'][$field]['url_endpoint']) ? $this->options['field_options'][$field]['url_endpoint'] : '',
      ];
      $form['field_options'][$field]['javascript_property'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Javascript property'),
        '#title_display' => 'invisible',
        '#placeholder' => 'The json property returned by the api endpoint to bind field to.',
        '#default_value' => isset($this->options['field_options'][$field]['javascript_property']) ? $this->options['field_options'][$field]['javascript_property'] : '',
      ];
      $form['field_options'][$field]['active'] = [
        '#title' => $this->t('Make @field endpoint active', ['@field' => $field]),
        '#title_display' => 'invisible',
        '#type' => 'checkbox',
        '#default_value' => !empty($this->options['field_options'][$field]['active']),
      ];
    }

    $form['update_cycle'] = [
      '#type' => 'number',
      '#title' => $this->t('Milliseconds/Seconds to update'),
      // Defaults to 5 seconds (5000 ms) intervals.
      '#default_value' => (isset($this->options['update_cycle'])) ? $this->options['update_cycle'] : 5000,
      '#description' => $this->t('How often should the endpoint(s) be polled.'),
    ];

    // Set by parent. We don't need to display this.
    // @todo - Do we need this ?
    $form['grouping'] = [];
  }

  /**
   * {@inheritdoc}
   */
  public function render() {

    $field_options = $this->options['field_options'];

    foreach ($field_options as $field => $options) {
      if ($field_options[$field]['active']) {
        $params = $this->view->getUrl()->getRouteParameters();
        // Test to see if the endpoint a token in it.
        $field_options[$field]['url_endpoint'] = $this->createUrlEndpoint($field_options[$field]['url_endpoint'], $params);
      }
    }

    $fields = $this->view->getHandlers('field');
    // This renders the view to the object.
    $this->renderFields($this->view->result);

    // @todo - look at creating a custom RenderElement.
    $vue_table = [
      '#markup' => '<div id="vue_table__app"></div>',
      '#attached' => [
        'library' => ['shp_vue_view/table'],
      ],
    ];

    $vue_table['#attached']['drupalSettings']['vue_table'] = [
      'field_options' => $field_options,
      'update_cycle' => $this->options['update_cycle'],
      'fields' => $fields,
      'view' => $this->rendered_fields,
    ];

    return $vue_table;
  }

  /**
   * Creates a url endpoint, replacing any valid tokens.
   *
   * @param string $path
   *   The endpoint to be parsed. Can contain tokens to matched.
   * @param array $params
   *   Arugments taken from the view route params.
   *
   * @return string
   *   The endpoint path.
   */
  protected function createUrlEndpoint(string $path, array $params = []) {
    foreach ($params as $key => $param) {
      // Perform a string replace on the uri.
      $path = str_replace('{' . $key . '}', $param, $path);
    }
    return $path;
  }

}
