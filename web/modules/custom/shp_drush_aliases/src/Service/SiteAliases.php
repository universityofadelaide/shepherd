<?php
/**
 * @file
 * Contains Drupal\shp_drush_aliases\Service\SiteAliases.
 */

namespace Drupal\shp_drush_aliases\Service;

use Drupal\node\Entity\Node;

/**
 * Class SiteAliases
 * @package Drupal\shp_drush_aliases\Service
 */
class SiteAliases {

  /**
   * Generate Drush aliases.
   *
   * @param \Drupal\node\Entity\Node $site
   *   A site node.
   *
   * @return string
   *   Generate PHP code containing Drush aliases for the given site.
   */
  public function generateAliases(Node $site) {
    // Load all related environments and site instances for a site.
    $entities = \Drupal::service('shp_custom.site')->loadRelatedEntities($site);

    $variables = $this->preprocessEntities($entities);

    // Render the Drush alias file.
    $twig = \Drupal::service('twig');
    $output = $twig->loadTemplate('modules/custom/shp_drush_aliases/templates/aliases.php.twig')->render($variables);

    return $output;
  }

  /**
   * Preprocess entities for use in the site aliases template.
   *
   * @param $entities
   * @return array
   */
  public function preprocessEntities($entities) {
    $environments = [];
    $site = reset($entities['shp_site']);
    $site_title = $site->title->value;

    foreach ($entities['shp_environment'] as $environment) {
      $machine_name = $environment->field_shp_machine_name->value;
      foreach ($entities['shp_site_instance'] as $site_instance) {
        $remote_host = reset($site_instance->field_shp_server->referencedEntities());
        if ($site_instance->field_shp_environment->target_id == $environment->id() &&
          !isset($environments[$machine_name])) {
          $environments[$machine_name . '_' . $site_instance->id()] = [
            'title' => $environment->title->value,
            'uri' => $environment->field_shp_domain_name->value,
            'site_instance_id' => $site_instance->id(),
            'remote_host' => $remote_host->field_shp_hostname->value,
            'ssh_port' => $site_instance->field_shp_ssh_port->value,
            'path-aliases' => [
              '%drush' => '/web/vendor/drush/drush',
              '%drush-script' => '/web/bin/drush',
            ],
          ];
        }
      }
    }

    return [
      'site_title' => $site_title,
      'environments' => $environments,
    ];
  }

}
