<?php

namespace Drupal\shp_orchestration;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A base class to implement an orchestration provider plugin.
 *
 * @see \Drupal\shp_orchestration\Annotation\OrchestrationProvider
 * @see \Drupal\shp_orchestration\OrchestrationProviderInterface
 */
abstract class OrchestrationProviderBase extends PluginBase implements ContainerFactoryPluginInterface, OrchestrationProviderInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function description() {
    // Retrieve description property from the annotation.
    return $this->pluginDefinition['description'];
  }

  /**
   * Retrieves the related config entity.
   */
  public function getConfigEntity() {
    return $this->configEntity;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateDeploymentName(string $id) {
    return 'node-' . $id;
  }

  /**
   * Generates a schedule name for a given deployment.
   *
   * @param string $deployment_name
   *   The deployment name.
   *
   * @return string
   *   The schedule name.
   */
  public static function generateScheduleName(string $deployment_name): string {
    return sprintf('%s-backup-scheduled', $deployment_name);
  }

  /**
   * Converts a string into a format acceptable for orchestration providers.
   *
   * Lowercase a-z0-9 with dashes.
   *
   * @param string $text
   *   The title to be sanitised.
   *
   * @return string
   *   sanitised title.
   */
  public static function sanitise($text) {
    return strtolower(preg_replace('/[\/\s]+/', '-', $text));
  }

}
