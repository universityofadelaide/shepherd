<?php

namespace Drupal\shp_orchestration;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerInterface;
use UniversityOfAdelaide\OpenShift\ClientException;

/**
 * A orchestration exception handler.
 */
class ExceptionHandler {

  use StringTranslationTrait;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * ExceptionHandler constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct(LoggerInterface $logger, MessengerInterface $messenger) {
    $this->logger = $logger;
    $this->messenger = $messenger;
  }

  /**
   * Handles OpenShift ClientExceptions.
   *
   * @param \UniversityOfAdelaide\OpenShift\ClientException $exception
   *   The exception to be handled.
   */
  public function handleClientException(ClientException $exception) {
    $reason = $exception->getMessage();
    if (strstr($exception->getBody(), 'Unauthorized')) {
      $reason = $this->t('Client is not authorized to access requested resource.');
    }

    $this->logger->error('An error occurred while communicating with OpenShift. %reason', [
      '%reason' => $reason,
    ]);

    $this->messenger->addError(t("An error occurred while communicating with OpenShift. %reason", ['%reason' => $reason]));
  }

}
