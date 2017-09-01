<?php

namespace Drupal\shp_backup\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ModalFormController
 *
 * @package Drupal\shp_backup\Controller
 */
class ModalFormController extends ControllerBase {

  /**
   * Form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * ModalFormController constructor.
   */
  public function __construct(FormBuilderInterface $formBuilder) {
    $this->formBuilder;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder')
    );
  }

  public function openForm($form) {
    $this->formBuilder->getForm('');
    $response = new AjaxResponse();
    $response->addCommand(new OpenModalDialogCommand('Dreams', 'WOOOOOOO'));
    return $response;
  }

}
