<?php

namespace Drupal\shp_redis_support\Entity;

use Drupal\shp_orchestration\Entity\OpenShiftConfigEntity;

/**
 * Defines the OpenShiftWithRedisConfigEntity.
 *
 * @todo Delete this entity type when shp_cache_backend is rolled out.
 *
 * @ConfigEntityType(
 *   id = "openshift_with_redis",
 *   label = @Translation("Openshift with redis config entity"),
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
 *     "namespace"
 *   }
 * )
 */
class OpenShiftWithRedisConfigEntity extends OpenShiftConfigEntity {

}
