<?php
/**
 * @file
 * Contains Drupal\ua_sm_drush_aliases\Service\SiteAliases.
 */

namespace Drupal\ua_sm_drush_aliases\Service;

use Drupal\node\Entity\Node;

/**
 * Class SiteAliases
 * @package Drupal\ua_sm_drush_aliases\Service
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
    $entities = \Drupal::service('ua_sm_custom.site')->loadRelatedEntities($site);

    $variables = $this->preprocessEntities($entities);

    // Render the Drush alias file.
    $twig = \Drupal::service('twig');
    $output = $twig->loadTemplate('modules/custom/ua_sm_drush_aliases/templates/aliases.php.twig')->render($variables);

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
    $site = reset($entities['ua_sm_site']);
    $site_title = $site->title->value;

    foreach ($entities['ua_sm_environment'] as $environment) {
      $machine_name = $environment->field_ua_sm_machine_name->value;
      foreach ($entities['ua_sm_site_instance'] as $site_instance) {
        if ($site_instance->field_ua_sm_environment->target_id == $environment->Id() &&
          !isset($environments[$machine_name])) {
          $environments[$machine_name] = [
            'title' => $environment->title->value,
            'uri' => $environment->field_ua_sm_domain_name->value,
            'site_instance_id' => $site_instance->id(),
            'remote_host' => $site_instance->field_ua_sm_hostname->value,
            'ssh_port' => $site_instance->field_ua_sm_ssh_port->value,
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
