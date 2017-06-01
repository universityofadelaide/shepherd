<?php

namespace Drupal\shp_orchestration\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the OpenShiftConfigEntity.
 *
 * @ConfigEntityType(
 *   id = "openshift",
 *   label = @Translation("Openshift config entity"),
 *   entity_keys = {
 *     "id" = "id",
 *     "endpoint" = "endpoint",
 *     "token" = "token",
 *     "namespace" = "namespace",
 *     "mode" = "mode"
 *   },
 *   handlers = {
 *      "form" = {
 *        "add" = "Drupal\shp_orchestration\Form\OpenShiftConfigEntityForm"
 *      }
 *   },
 *   links = {
 *    "edit-form" = "/admin/config/system/shepherd/orchestration/provider_settings"
 *   }
 * )
 */
class OpenShiftConfigEntity extends ConfigEntityBase {

  /**
   * The endpoint to the OpenShift API.
   *
   * @var string
   */
  public $endpoint;

  /**
   * The security token for authentication.
   *
   * @var string
   */
  public $token;

  /**
   * Cluster namespace to run queries in.
   *
   * @var string
   */
  public $namespace;

  /**
   * If provider is in development, uat or production mode.
   *
   * @var string
   */
  public $mode;

  /**
   * Entity ID.
   *
   * @var string
   */
  public $id;
}
