<?php

namespace Drupal\shp_backup\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A form to set config for the Shepherd and its integrations.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Used to render the pretty tokenizer output.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * ShepherdSettings constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * @param \Drupal\Core\Render\RendererInterface $renderer
   */
  public function __construct(ConfigFactoryInterface $config_factory, RendererInterface $renderer) {
    parent::__construct($config_factory);

    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shp_backup_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['shp_backup.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('shp_backup.settings');

    $token_tree = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['shp_backup'],
    ];
    $rendered_token_tree = $this->renderer->render($token_tree);

    $form['root_dir'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Backup root directory'),
      '#description' => 'Directory path to the root of the backup directory, no trailing slash required.',
      '#default_value' => $config->get('root_dir'),
    ];

    $form['backup_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default backup title'),
      '#description' => t('Enter the default backup title when a backup is created, This field supports tokens. @browse_tokens_link', ['@browse_tokens_link' => $rendered_token_tree]),
      '#default_value' => $config->get('backup_title'),
      '#element_validate' => [
        'token_element_validate',
      ],
    ];

    $form['backup_command'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Backup command'),
      '#description' => t('Commands to run to backup a site. Each command on a new line will be combined with && when run to stop on any error. This field supports tokens. @browse_tokens_link', ['@browse_tokens_link' => $rendered_token_tree]),
      '#default_value' => $config->get('backup_command'),
      '#element_validate' => [
        'token_element_validate',
      ],
      '#token_types' => [
        'shp_backup',
      ],
    ];

    $form['restore_command'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Restore command'),
      '#description' => t('Commands to run to restore a site. Each command on a new line will be combined with && when run to stop on any error. This field supports tokens. @browse_tokens_link', ['@browse_tokens_link' => $rendered_token_tree]),
      '#default_value' => $config->get('restore_command'),
      '#element_validate' => [
        'token_element_validate',
      ],
      '#token_types' => [
        'shp_backup',
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('shp_backup.settings')
      ->set('root_dir', '/' . trim($form_state->getValue(['root_dir']), '/'))
      ->set('root_dir', '/' . trim($form_state->getValue(['root_dir']), '/'))
      ->set('backup_title', $form_state->getValue(['backup_title']))
      ->set('backup_command', $form_state->getValue(['backup_command']))
      ->set('restore_command', $form_state->getValue(['restore_command']))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
