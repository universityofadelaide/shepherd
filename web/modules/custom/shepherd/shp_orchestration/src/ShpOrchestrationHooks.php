<?php

namespace Drupal\shp_orchestration;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Site\Settings;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hook bridges for the shp_orchestration module.
 */
class ShpOrchestrationHooks implements ContainerInjectionInterface {

  /**
   * The settings object.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected $settings;

  /**
   * ShpOrchestrationHooks constructor.
   *
   * @param \Drupal\Core\Site\Settings $settings
   *   The settings object.
   */
  public function __construct(Settings $settings) {
    $this->settings = $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('settings')
    );
  }

  /**
   * Hook bridge for shp_orchestration_shp_env_vars.
   *
   * @param \Drupal\node\NodeInterface $environment
   *   The environment node.
   *
   * @return array
   *   An array of environment variables.
   */
  public function shpEnvVars(NodeInterface $environment) {
    /** @var Drupal\Node\NodeInterface $site */
    $site = $environment->field_shp_site->entity;

    // Generate default environment variables.
    $env_vars = [
      'SHEPHERD_SITE_ID' => $site->id(),
      'SHEPHERD_URL' => \Drupal::service('router.request_context')->getCompleteBaseUrl(),
      // @todo Ensure deployment secret has SHEPHERD_TOKEN set.
      'SHEPHERD_TOKEN_FILE' => '/etc/secret/SHEPHERD_TOKEN',
      'WEB_PATH' => $environment->field_shp_path->value,
    ];

    // Add NewRelic environment variables if enabled.
    if ($environment->field_newrelic_enabled->value) {
      $secrets = $this->settings->get('shepherd_secrets', []);
      if (isset($secrets['SHEPHERD_NEW_RELIC_LICENSE'])) {
        $env_vars['NEW_RELIC_ENABLED'] = 'true';
        $env_vars['NEW_RELIC_LICENSE_KEY'] = preg_replace('/\r|\n/', '', $secrets['SHEPHERD_NEW_RELIC_LICENSE']);
        $env_vars['NEW_RELIC_APP_NAME'] = sprintf('%s-%s', $site->field_shp_short_name->value, $environment->id());
      }
    }

    return $env_vars;
  }

}
