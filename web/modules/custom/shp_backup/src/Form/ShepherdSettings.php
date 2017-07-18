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
class ShepherdSettings extends ConfigFormBase {

  /**
   * @var \Drupal\Core\Render\Renderer
   *   Used to render the pretty tokenizer output.
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
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *
   * @return static
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
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('shp_backup.settings');

    $token_tree = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['shp_backup'],
    ];
    $rendered_token_tree = $this->renderer->render($token_tree);

    $form['backup_service'] = [
      '#type' => 'details',
      '#title' => $this->t('Backups'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $form['backup_service']['root_dir'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Backup root directory'),
      '#description' => 'Directory path to the root of the backup directory, no trailing slash required.',
      '#default_value' => $config->get('backup_service.root_dir'),
    ];

    $form['backup_service']['backup_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default backup title'),
      '#description' => t('Enter the default backup title when a backup is created, This field supports tokens. @browse_tokens_link', ['@browse_tokens_link' => $rendered_token_tree]),
      '#default_value' => $config->get('backup_service.backup_title'),
      '#element_validate' => [
        'token_element_validate',
      ],
    ];

    $form['backup_service']['backup_command'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Backup command'),
      '#description' => t('Commands to run to backup a site. Each command on a new line will be combined with && when run to stop on any error. This field supports tokens. @browse_tokens_link', ['@browse_tokens_link' => $rendered_token_tree]),
      '#default_value' => $config->get('backup_service.backup_command'),
      '#element_validate' => [
        'token_element_validate',
      ],
      '#token_types' => [
        'shp_backup',
      ],
    ];

    $form['backup_service']['restore_command'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Restore command'),
      '#description' => t('Commands to run to restore a site. Each command on a new line will be combined with && when run to stop on any error. This field supports tokens. @browse_tokens_link', ['@browse_tokens_link' => $rendered_token_tree]),
      '#default_value' => $config->get('backup_service.restore_command'),
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
    $config = $this->config('shp_backup.settings');
    $config->delete();

    $values = $form_state->getValue('backup_service');
    $config->set('backup_service.root_dir', '/' . trim($values['root_dir'], '/'));
    $config->set('backup_service.backup_title', $values['backup_title']);
    $config->set('backup_service.backup_command', $values['backup_command']);
    $config->set('backup_service.restore_command', $values['restore_command']);
    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['shp_backup.settings'];
  }

}
