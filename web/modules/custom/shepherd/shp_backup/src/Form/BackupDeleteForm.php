<?php

namespace Drupal\shp_backup\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\shp_orchestration\OrchestrationProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * BackupDeleteForm.
 *
 * Deletes a backup.
 *
 * @package Drupal\shp_backup\Form
 */
class BackupDeleteForm extends FormBase {

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
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.orchestration_provider')->getProviderInstance(),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'shp_backup_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $site = NULL, $backupName = NULL): array {
    if ($site === NULL) {
      return [
        '#markup' => '<p>Invalid request: missing site context.</p>',
      ];
    }

    $form_state->set('site', $site->id());
    $backup = $this->orchestrationProvider->getBackup($site->id(), $backupName);
    if (!$backup) {
      return [
        '#markup' => "<p>An error occurred while communicating with OpenShift.</p>",
      ];
    }
    $form_state->set('backupName', $backupName);
    $form['confirm'] = [
      '#markup' => $this->t("<p>Are you sure you want to delete the Backup <strong>@name</strong>.</p>", ['@name' => $backup->getFriendlyName()]),
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Confirm'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $name = $form_state->get('backupName');
    $site_id = $form_state->get('site');
    $backup = $this->orchestrationProvider->getBackup($site_id, $name);
    if ($this->orchestrationProvider->deleteBackup($site_id, $name)) {
      $label = $backup ? $backup->getFriendlyName() : $name;
      $this->messenger->addStatus($this->t('Successfully deleted backup @name', ['@name' => $label]));
    }
    else {
      $label = $backup ? $backup->getFriendlyName() : $name;
      $this->messenger->addError($this->t('There was an issue deleting backup @name', ['@name' => $label]));
    }

    $form_state->setRedirectUrl(Url::fromRoute('shp_backup.backups', ['node' => $site_id]));
  }

}
