<?php

namespace Drupal\shp_backup\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\shp_orchestration\OrchestrationProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UniversityOfAdelaide\OpenShift\Objects\Annotation;
use UniversityOfAdelaide\OpenShift\Objects\Backups\Backup;

/**
 * BackupEditForm.
 *
 * Edits a backup.
 *
 * @package Drupal\shp_backup\Form
 */
class BackupEditForm extends FormBase {

  /**
   * The orchestration provider plugin.
   *
   * @var \Drupal\shp_orchestration\OrchestrationProviderInterface
   */
  protected $orchestrationProvider;

  /**
   * Messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * BackupDeleteForm constructor.
   *
   * @param \Drupal\shp_orchestration\OrchestrationProviderInterface $orchestrationProvider
   *   The orchestration provider plugin.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct(OrchestrationProviderInterface $orchestrationProvider, MessengerInterface $messenger) {
    $this->orchestrationProvider = $orchestrationProvider;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.orchestration_provider')->getProviderInstance(),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shp_backup_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $site = NULL, $backupName = NULL) {
    $form_state->set('site', $site->id());
    $backup = $this->orchestrationProvider->getBackup($site->id(), $backupName);
    if (!$backup) {
      return [
        '#markup' => "<p>An error occurred while communicating with OpenShift.</p>",
      ];
    }
    $form_state->set('backupName', $backupName);
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#description' => $this->t('Change the name of this backup'),
      '#required' => TRUE,
      '#default_value' => $backup->getFriendlyName(),
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $name = $form_state->get('backupName');
    $site_id = $form_state->get('site');
    $backup = $this->orchestrationProvider->getBackup($site_id, $name);
    $backup->setAnnotation(Annotation::create(Backup::FRIENDLY_NAME_ANNOTATION, $form_state->getValue('name')));
    if ($this->orchestrationProvider->updateBackup($site_id, $backup)) {
      $this->messenger->addStatus($this->t('Successfully updated backup @name', ['@name' => $backup->getFriendlyName()]));
    }
    else {
      $this->messenger->addError($this->t('There was an issue updating backup @name', ['@name' => $backup->getFriendlyName()]));
    }

    $form_state->setRedirectUrl(Url::fromRoute('shp_backup.backups', ['node' => $site_id]));
  }

}
