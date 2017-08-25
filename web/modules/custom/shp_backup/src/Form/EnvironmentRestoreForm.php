<?php

namespace Drupal\shp_backup\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\shp_backup\Service\Backup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SiteCloneForm.
 *
 * @package Drupal\shp_backup\Form
 */
class EnvironmentRestoreForm extends FormBase {

  /**
   * Used to trigger restores via the backup service.
   *
   * @var \Drupal\shp_backup\Service\Backup
   */
  protected $backup;

  /**
   * EnvironmentRestoreForm constructor.
   *
   * @param \Drupal\shp_backup\Service\Backup $backup
   *   The backup service.
   */
  public function __construct(Backup $backup) {
    $this->backup = $backup;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('shp_backup.backup')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shp_backup_environment_restore_form';
  }

  /**
   * Callback to get page title for the name of the site.
   *
   * @param \Drupal\node\NodeInterface $site
   *   Site node.
   * @param \Drupal\node\NodeInterface $environment
   *   Evnironment node.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Translated markup.
   */
  public function getPageTitle(NodeInterface $site, NodeInterface $environment) {
    return t('Restore environment - @site_title : @environment_title', ['@site_title' => $site->getTitle(), '@environment_title' => $environment->getTitle()]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $site = NULL, NodeInterface $environment = NULL) {
    $form_state->set('site', $site);
    $form_state->set('environment', $environment);

    $backups = $this->backup->getAll($site);

    $backup_options = [];

    foreach ($backups as $backup) {
      $backup_options[$backup->nid] = $backup->_entity->title->value;
    }

    $build = [
      'backup' => [
        '#type' => 'select',
        '#title' => $this->t('Backup path'),
        '#options' => $backup_options,
        '#required' => TRUE,
      ],
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Restore now'),
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Shepherd: Completely refactor restore for shepherd.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->cleanValues();

    $site = $form_state->get('site');
    $environment = $form_state->get('environment');

    $backup = Node::load($form_state->getValue('backup'));

    // Set the backup to restore from.
    // @todo Inject the service.
    $status = \Drupal::service('shp_orchestration.job_queue')->add(
      $backup,
      'shp_restore',
      'shp_backup.backup',
      ['environment' => $environment->id()]
    );
    if ($status) {
      drupal_set_message($this->t('Restore has been queued for %title', [
        '%title' => $environment->getTitle(),
      ]));
    }
    else {
      drupal_set_message($this->t('Restore failed. Could not find any instances for %title',
        [
          '%title' => $environment->getTitle(),
        ]), 'error');
    }

    $form_state->setRedirect("entity.node.canonical", ['node' => $site->id()]);
  }

}
