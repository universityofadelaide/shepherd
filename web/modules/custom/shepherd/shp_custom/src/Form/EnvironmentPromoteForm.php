<?php

namespace Drupal\shp_custom\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\shp_custom\Service\Site;
use Drupal\shp_orchestration\Service\Environment;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * EnvironmentPromoteForm.
 *
 * Allows promoting environments.
 *
 * @package Drupal\shp_custom\Form
 */
class EnvironmentPromoteForm extends FormBase {

  /**
   * Shepherd orchestration environment.
   *
   * @var \Drupal\shp_orchestration\Service\Environment
   *   Orchestration environment.
   */
  protected $environment;

  /**
   * Shepherd custom site.
   *
   * @var \Drupal\shp_custom\Service\Site
   *   Shepherd custom site.
   */
  protected $site;

  /**
   * Messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * EnvironmentPromoteForm constructor.
   *
   * @param \Drupal\shp_orchestration\Service\Environment $environment
   *   Shepherd orchestration environment.
   * @param \Drupal\shp_custom\Service\Site $site
   *   Shepherd custom site.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct(Environment $environment, Site $site, MessengerInterface $messenger) {
    $this->environment = $environment;
    $this->site = $site;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('shp_orchestration.environment'),
      $container->get('shp_custom.site'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shp_custom_environment_promote_form';
  }

  /**
   * Callback to get page title for the name of the site.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Translated markup.
   */
  public function getPageTitle() {
    return $this->t('Promote environment to production');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $site = NULL, NodeInterface $environment = NULL) {
    $form_state->set('site', $site);
    $form_state->set('environment', $environment);

    // @todo Lookup existing prod and list on this form.
    $form['site'] = [
      '#type' => 'item',
      '#title' => $this->t('Site'),
      '#markup' => $site->getTitle(),
    ];
    $form['production_url'] = [
      '#type' => 'item',
      '#title' => $this->t('Production url'),
      'url' => Link::fromTextAndUrl(
        $site->field_shp_domain->value . $site->field_shp_path->value,
        Url::fromUri('//' . $site->field_shp_domain->value . $site->field_shp_path->value))->toRenderable(),
    ];
    $form['promote_environment'] = [
      '#type' => 'item',
      '#title' => $this->t('Environment to promote'),
      'url' => Link::fromTextAndUrl(
        $environment->getTitle(),
        Url::fromUri('//' . $environment->getTitle()))->toRenderable(),
    ];
    $form['warning'] = [
      '#type' => 'item',
      '#title' => $this->t('Warning'),
      '#description' => $this->t('Please ensure the new environment is production ready and you have been authorised to make the change.'),
    ];

    // @todo everything is exclusive for now, implement non-exclusive?
    // I.e. exclusive means routing traffic to more than one deployment.
    // $form['exclusive'] = [
    // '#title' => $this->t('Make this environment the exclusive destination?'),
    // '#type' => 'checkbox',
    // '#default_value' => FALSE,
    // ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Promote now'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->cleanValues();

    $site = $form_state->get('site');
    $environment = $form_state->get('environment');
    // $form_state->getValue('exclusive');
    $exclusive = TRUE;

    if ($this->environment->promoted($site, $environment, $exclusive)) {
      $this->messenger->addStatus($this->t('Promoted %environment for %site successfully', [
        '%environment' => $environment->getTitle(),
        '%site' => $site->getTitle(),
      ]));
      $this->site->setGoLiveDate($environment);
    }
    else {
      $this->messenger->addError($this->t('Failed to promote %environment for %site',
        [
          '%environment' => $environment->getTitle(),
          '%site' => $site->getTitle(),
        ]));
    }

    $form_state->setRedirect("entity.node.canonical", ['node' => $site->id()]);
  }

}
