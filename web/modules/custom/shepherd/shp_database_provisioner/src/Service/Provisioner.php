<?php

namespace Drupal\shp_database_provisioner\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\shp_custom\Service\Environment;
use Drupal\shp_custom\Service\StringGenerator;
use Drupal\shp_orchestration\OrchestrationProviderPluginManagerInterface;

/**
 * A service to provision databases.
 */
class Provisioner {

  // Secret key names used to store database credentials.
  public const ENV_MYSQL_HOSTNAME = 'DATABASE_HOST';

  public const ENV_MYSQL_PORT = 'DATABASE_PORT';

  public const ENV_MYSQL_DATABASE = 'DATABASE_NAME';

  public const ENV_MYSQL_USERNAME = 'DATABASE_USER';

  public const ENV_MYSQL_PASSWORD = 'DATABASE_PASSWORD';

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Orchestration provider.
   *
   * @var \Drupal\shp_orchestration\OrchestrationProviderInterface
   */
  protected $orchestrationProviderPlugin;

  /**
   * Environment service.
   *
   * @var \Drupal\shp_custom\Service\Environment
   */
  protected $environmentService;

  /**
   * String generator service.
   *
   * @var \Drupal\shp_custom\Service\StringGenerator
   */
  protected $stringGenerator;

  /**
   * Provisioner constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory service.
   * @param \Drupal\shp_orchestration\OrchestrationProviderPluginManagerInterface $orchestrationProviderPluginManager
   *   Orchestration provider plugin manager.
   * @param \Drupal\shp_custom\Service\Environment $environmentService
   *   Environment service.
   * @param \Drupal\shp_custom\Service\StringGenerator $stringGenerator
   *   String generator service.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    OrchestrationProviderPluginManagerInterface $orchestrationProviderPluginManager,
    Environment $environmentService,
    StringGenerator $stringGenerator
  ) {
    $this->configFactory = $configFactory;
    $this->orchestrationProviderPlugin = $orchestrationProviderPluginManager->getProviderInstance();
    $this->environmentService = $environmentService;
    $this->stringGenerator = $stringGenerator;
  }

  /**
   * Create a user, database and secret.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Environment entity.
   *
   * @return bool
   *   True if everything was successfully created otherwise false.
   */
  public function create(EntityInterface $entity): bool {
    $config = $this->configFactory->get('shp_database_provisioner.settings');
    if ($entity->bundle() !== 'shp_environment' && !$config->get('enabled')) {
      return FALSE;
    }

    $site = $this->environmentService->getSite($entity);
    if (!$site || !isset($site->field_shp_project->target_id)) {
      // @todo Handle errors.
      return FALSE;
    }

    $deployment_name = $this->orchestrationProviderPlugin::generateDeploymentName($entity->id());

    // Construct credentials for the new environment.
    $database = 'env_' . $entity->id();
    $username = 'user_' . $entity->id();
    $password = $this->stringGenerator->generateRandomPassword();

    $host = $config->get('host');
    $port = $config->get('port');
    $options = $config->get('options');
    // Save the credentials in a secret for use by the environment.
    if (!$this->setSecret($host, $port, $database, $username, $password,
      $deployment_name)) {
      // @todo Handle errors.
      return FALSE;
    }

    // Fetch privileged database password from orchestration secret store.
    $privileged_username = $config->get('user');
    $privileged_password = $this->orchestrationProviderPlugin->getSecret($config->get('secret'),
      'DATABASE_PASSWORD');
    $db = new \mysqli($host, $privileged_username, $privileged_password, NULL,
      $port, NULL);

    return $this->createDatabase($database, $db) &&
      $this->createUser($database, $username, $password, $db, $options);
  }

  /**
   * Create or update environment secret with database info and credentials.
   *
   * @param string $host
   *   Database host name.
   * @param string $port
   *   Database port.
   * @param string $database
   *   Database name.
   * @param string $username
   *   Environment database user name.
   * @param string $password
   *   Environment database password.
   * @param string $deployment_name
   *   Deployment name to generate secret name.
   *
   * @return array|bool
   *   Secret data on success otherwise false.
   */
  public function setSecret(
    string $host,
    string $port,
    string $database,
    string $username,
    string $password,
    string $deployment_name
  ) {
    // Add database credentials to deployment secret.
    // Create the secret if it doesn't exist, otherwise add it to the existing.
    $secret_data = [
      static::ENV_MYSQL_HOSTNAME => $host,
      static::ENV_MYSQL_PORT => $port,
      static::ENV_MYSQL_DATABASE => $database,
      static::ENV_MYSQL_USERNAME => $username,
      static::ENV_MYSQL_PASSWORD => $password,
    ];

    // Create or update the secret as needed.
    if ($env_secret = $this->orchestrationProviderPlugin->getSecret($deployment_name)) {
      return $this->orchestrationProviderPlugin->updateSecret($deployment_name,
        array_merge($env_secret, $secret_data));
    }
    return $this->orchestrationProviderPlugin->createSecret($deployment_name,
      $secret_data);
  }

  /**
   * Create a database for the environment.
   *
   * @param string $database
   *   Database name.
   * @param \mysqli $db
   *   Privileged database connection.
   *
   * @return bool
   *   True on success otherwise false.
   */
  public function createDatabase(string $database, \mysqli $db): bool {
    $query = sprintf('CREATE DATABASE `%s`', $database);
    $statement = $db->prepare($query);
    if ($statement === NULL || $statement === FALSE) {
      // @todo Handle errors.
      return FALSE;
    }
    return $statement->execute();
  }

  /**
   * Create a database for the environment.
   *
   * @param string $database
   *   Database name.
   * @param \mysqli $db
   *   Privileged database connection.
   *
   * @return bool
   *   True on success otherwise false.
   */
  public function dropDatabase(string $database, \mysqli $db): bool {
    $query = sprintf('DROP DATABASE `%s`', $database);
    $statement = $db->prepare($query);
    if ($statement === NULL || $statement === FALSE) {
      // @todo Handle errors.
      return FALSE;
    }
    return $statement->execute();
  }

  /**
   * Creates a database user and privileges for the environment.
   *
   * @param string $database
   *   Environment database name.
   * @param string $username
   *   Environment database user name.
   * @param string $password
   *   Environment database password.
   * @param \mysqli $db
   *   Privileged database connection.
   * @param string $options
   *   Grant options.
   *
   * @return bool
   *   True on success otherwise false.
   */
  public function createUser(
    string $database,
    string $username,
    string $password,
    \mysqli $db,
    string $options = ''
  ): bool {
    $query = sprintf(
      "GRANT ALL PRIVILEGES ON `%s`.* TO `%s`@`%%` IDENTIFIED BY '%s'",
      $database,
      $username,
      $password
    );
    if (!empty($options)) {
      $query .= sprintf(' WITH %s', $options);
    }

    $statement = $db->prepare($query);
    if ($statement === NULL || $statement === FALSE) {
      // @todo Handle errors.
      return FALSE;
    }
    return $statement->execute();
  }

  /**
   * Creates a database user and privileges for the environment.
   *
   * @param string $username
   *   Environment database user name.
   * @param \mysqli $db
   *   Privileged database connection.
   *
   * @return bool
   *   True on success otherwise false.
   */
  public function dropUser(
    string $username,
    \mysqli $db
  ): bool {
    $query = sprintf(
      "DROP USER `%s`@`%%`",
      $username
    );
    $statement = $db->prepare($query);
    if ($statement === NULL || $statement === FALSE) {
      // @todo Handle errors.
      return FALSE;
    }
    return $statement->execute();
  }

}
