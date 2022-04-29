<?php

namespace Drupal\shp_orchestration\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the OpenShiftConfigEntity.
 *
 * @todo Delete this entity type when shp_cache_backend is rolled out.
 *
 * @ConfigEntityType(
 *   id = "openshift",
 *   label = @Translation("Openshift config entity"),
 *   entity_keys = {
 *     "id" = "id",
 *     "endpoint" = "endpoint",
 *     "token" = "token",
 *     "namespace" = "namespace",
 *     "verify_tls" = "verify_tls",
 *     "uid" = "uid",
 *     "gid" = "gid"
 *   },
 *   handlers = {
 *      "form" = {
 *        "add" = "Drupal\shp_orchestration\Form\OpenShiftConfigEntityForm"
 *      }
 *   },
 *   links = {
 *    "edit-form" = "/admin/config/system/shepherd/orchestration/provider_settings"
 *   },
 *   config_export = {
 *     "id",
 *     "endpoint",
 *     "namespace",
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
   * Verify OpenShift API TLS certificate.
   *
   * @var bool
   */
  public $verify_tls;

  /**
   * Default uid containers should run as.
   *
   * @var string
   */
  public $uid;

  /**
   * Default gid containers should run as.
   *
   * @var string
   */
  public $gid;

  /**
   * Entity ID.
   *
   * @var string
   */
  public $id;

}
