<?php

namespace Drupal\shp_orchestration\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UniversityOfAdelaide\OpenShift\Client;
use UniversityOfAdelaide\OpenShift\ClientException;

/**
 * {@inheritdoc}
 */
class OpenShiftConfigEntityForm extends EntityForm {
  use StringTranslationTrait;

  /**
   * Messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * OpenShiftConfigEntityForm constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $entity = $this->entity;

    $form['id'] = [
      '#type' => 'value',
      '#value' => $entity->getEntityTypeId(),
    ];
    $form['endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Endpoint'),
      '#default_value' => $entity->endpoint,
      '#required' => TRUE,
    ];
    $form['verify_tls'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Verify TLS'),
      '#default_value' => $entity->verify_tls,
    ];
    $form['token'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Token'),
      '#default_value' => $entity->token,
      '#attributes' => ['autocomplete' => 'off'],
      '#required' => TRUE,
    ];
    $form['namespace'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Project'),
      '#default_value' => $entity->namespace,
      '#description' => $this->t("The OpenShift project to use."),
      '#required' => FALSE,
    ];
    $form['uid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User ID'),
      '#description' => $this->t("The default user id containers should run as."),
      '#default_value' => $entity->uid,
      '#required' => FALSE,
    ];
    $form['gid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Group ID'),
      '#description' => $this->t("The default group id containers should run as."),
      '#default_value' => $entity->gid,
      '#required' => FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    parent::validateForm($form, $form_state);

    $client_response = $this->validateClientConfiguration(
      $form['endpoint']['#value'],
      $form['token']['#value'],
      $form['namespace']['#value'],
      $form['verify_tls']['#checked']
    );

    if (isset($client_response['error']) && $client_response['error']) {
      $field_name = $this->getErrorFieldName($client_response);
      $form_state->setErrorByName($field_name,
        $this->t('Error: %response_code - %message', [
          '%response_code' => $client_response['code'],
          '%message' => !empty($client_response['message']) ? $client_response['message'] : "Can't connect, check that OpenShift is accessible.",
        ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = $entity->save();
    if ($status == SAVED_UPDATED) {
      $this->messenger->addStatus($this->t('OpenShift configuration has been updated'));
    }
    else {
      $this->messenger->addStatus($this->t('OpenShift configuration has been saved'));
    }
  }

  /**
   * Validates the configuration against the openshift client.
   *
   * @param string $endpoint
   *   The hostname.
   * @param string $token
   *   A generated auth token.
   * @param string $namespace
   *   Namespace/project on which to operate methods on.
   * @param bool $verify_tls
   *   Verify tls certificates.
   *
   * @return array
   *   Resource list if valid otherwise an array with error code and message.
   */
  protected function validateClientConfiguration($endpoint, $token, $namespace, $verify_tls) {
    $client = new Client(
      $endpoint,
      $token,
      $namespace,
      $verify_tls
    );

    try {
      // Make a request to the API resource list.
      $response = $client->request('GET', '/oapi/v1/namespaces/' . $namespace);
    }
    catch (ClientException $e) {
      $body = $e->getBody();
      $response = [
        'error' => TRUE,
        'code' => $e->getCode(),
        'message' => $body ? $body : $e->getMessage(),
      ];
    }

    return $response;
  }

  /**
   * Determines field to display the error message on based on the response.
   *
   * @param array $client_response
   *   Response from the client.
   *
   * @return string
   *   Field name to attach error message to.
   */
  protected function getErrorFieldName(array $client_response): string {
    $field_name = '';
    switch ($client_response['code']) {
      // Some curl errors return a non http code - 0.
      case FALSE:
        // Not available at the endpoint or SSL Certificate issue.
        $field_name = 'endpoint';
        break;

      case 401:
        // Token is invalid.
        $field_name = 'token';
        break;

      case 403:
        // Cannot access namespace with token.
        $field_name = 'namespace';
        break;
    }
    return $field_name;
  }

}
