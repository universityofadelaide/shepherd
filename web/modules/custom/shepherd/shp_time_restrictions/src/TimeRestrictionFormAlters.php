<?php

namespace Drupal\shp_time_restrictions;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormStateInterface;

/**
 * Service description.
 */
class TimeRestrictionFormAlters {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The NodeActionLogger service.
   *
   * @var \Drupal\shp_time_restrictions\ActionsLogService
   */
  protected ActionsLogService $actionsLogService;

  /**
   * Constructs a FormAlters object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\shp_time_restrictions\ActionsLogService $actionsLogService
   *   The Actions Log service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Connection $connection, ActionsLogService $actionsLogService) {
    $this->configFactory = $config_factory;
    $this->connection = $connection;
    $this->actionsLogService = $actionsLogService;
  }

  /**
   * Form alter to prevent Concurrent Deployments.
   */
  public function preventConcurrentActionsFormAlter(array &$form, FormStateInterface $form_state, int $nid = NULL) {
    $form['#validate'][] = [
      TimeRestrictionFormAlters::class,
      'preventConcurrentDeploymentsValidate',
    ];

    if (!$this->actionsLogService->isOutsideEnvironmentCreationTimeDelay($nid)) {
      \Drupal::messenger()->addError(
        t('To prevent errors it is not possible to create an environment at this time')
      );
      $form['#access'] = FALSE;
    }
  }

  /**
   * Validate form can not be submitted after a recent environment creation.
   */
  public static function preventConcurrentDeploymentsValidate(array &$form, FormStateInterface $form_state) {
    if (!\Drupal::service('shp_time_restrictions.actions_log')->isOutsideEnvironmentCreationTimeDelay()) {
      \Drupal::messenger()->addError(t('To prevent errors it is not possible to create an environment at this time'));
      $form_state->setErrorByName('', 'To prevent errors it is not possible to create an environment at this time');
    }
  }

}
