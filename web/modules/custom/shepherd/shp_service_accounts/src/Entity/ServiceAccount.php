<?php

namespace Drupal\shp_service_accounts\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\shp_service_accounts\ServiceAccountInterface;

/**
 * Defines the service account entity type.
 *
 * @ConfigEntityType(
 *   id = "service_account",
 *   label = @Translation("Service Account"),
 *   label_collection = @Translation("Service Accounts"),
 *   label_singular = @Translation("service account"),
 *   label_plural = @Translation("service accounts"),
 *   label_count = @PluralTranslation(
 *     singular = "@count service account",
 *     plural = "@count service accounts",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\shp_service_accounts\ServiceAccountListBuilder",
 *     "form" = {
 *       "add" = "Drupal\shp_service_accounts\Form\ServiceAccountForm",
 *       "edit" = "Drupal\shp_service_accounts\Form\ServiceAccountForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "service_account",
 *   admin_permission = "administer service_account",
 *   links = {
 *     "collection" = "/admin/config/shepherd/service-account",
 *     "add-form" = "/admin/config/shepherd/service-account/add",
 *     "edit-form" = "/admin/config/shepherd/service-account/{service_account}",
 *     "delete-form" = "/admin/config/shepherd/service-account/{service_account}/delete"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description"
 *   }
 * )
 */
class ServiceAccount extends ConfigEntityBase implements ServiceAccountInterface {

  /**
   * The service account ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The service account label.
   *
   * @var string
   */
  protected $label;

  /**
   * The service account status.
   *
   * @var bool
   */
  protected $status;

  /**
   * The service account description.
   *
   * @var string
   */
  protected $description;

  /**
   * The service account token.
   *
   * @var string
   */
  protected $token;

}
