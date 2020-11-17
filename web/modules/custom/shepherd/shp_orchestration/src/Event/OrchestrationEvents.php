<?php

namespace Drupal\shp_orchestration\Event;

/**
 * Orchestration event constants.
 */
final class OrchestrationEvents {

  // Define event types that we will trigger on.
  const SETUP_ENVIRONMENT   = 'shp_orchestration.setup_environment';
  const CREATED_ENVIRONMENT = 'shp_orchestration.created_environment';
  const UPDATED_ENVIRONMENT = 'shp_orchestration.updated_environment';
  const DELETED_ENVIRONMENT = 'shp_orchestration.deleted_environment';

}
