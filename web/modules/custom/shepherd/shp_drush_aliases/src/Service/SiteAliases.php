<?php

namespace Drupal\shp_drush_aliases\Service;

use Drupal\node\Entity\Node;

/**
 * Class SiteAliases.
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
   *
   * @return array
   */
  public function preprocessEntities($entities) {
    // @todo - provide drush aliases for orchestration provider.
  }

}
