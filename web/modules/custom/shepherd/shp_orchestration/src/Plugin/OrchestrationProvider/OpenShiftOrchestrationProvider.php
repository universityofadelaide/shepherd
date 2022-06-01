<?php

namespace Drupal\shp_orchestration\Plugin\OrchestrationProvider;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\shp_custom\Service\StringGenerator;
use Drupal\shp_orchestration\ExceptionHandler;
use Drupal\shp_orchestration\OrchestrationProviderBase;
use Drupal\shp_orchestration\TokenNamespaceTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UniversityOfAdelaide\OpenShift\Client as OpenShiftClient;
use UniversityOfAdelaide\OpenShift\ClientException;
use UniversityOfAdelaide\OpenShift\Objects\Backups\Backup;
use UniversityOfAdelaide\OpenShift\Objects\Backups\Database;
use UniversityOfAdelaide\OpenShift\Objects\Backups\Restore;
use UniversityOfAdelaide\OpenShift\Objects\Backups\ScheduledBackup;
use UniversityOfAdelaide\OpenShift\Objects\Backups\Sync;
use UniversityOfAdelaide\OpenShift\Objects\Hpa;
use UniversityOfAdelaide\OpenShift\Objects\Route;
use UniversityOfAdelaide\OpenShift\Objects\Label;

/**
 * The openshift orchestration provider.
 *
 * @OrchestrationProvider(
 *   id = "openshift_orchestration_provider",
 *   name = "OpenShift",
 *   description = @Translation("OpenShift provider to perform orchestration tasks"),
 * )
 */
class OpenShiftOrchestrationProvider extends OrchestrationProviderBase {

  use TokenNamespaceTrait;

  /**
   * Define keys used for MySQL connectivity by the backup operator.
   *
   * @see https://github.com/universityofadelaide/shepherd-operator/blob/master/pkg/apis/meta/v1/types.go#L34
   */
  // phpcs:disable Generic.NamingConventions.UpperCaseConstantName
  protected const KeyMySQLHostname = 'hostname';
  protected const KeyMySQLDatabase = 'database';
  protected const KeyMySQLPort = 'port';
  protected const KeyMySQLUsername = 'username';
  protected const KeyMySQLPassword = 'password';
  // phpcs:enable

  /**
   * OpenShift client.
   *
   * @var \UniversityOfAdelaide\OpenShift\Client
   *   PHP OpenShift client.
   */
  protected $client;

  /**
   * Shepherd custom string generator.
   *
   * @var \Drupal\shp_custom\Service\StringGenerator
   *   String generator.
   */
  protected $stringGenerator;

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The config entity.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The orchestration exception handler.
   *
   * @var \Drupal\shp_orchestration\ExceptionHandler
   */
  protected $exceptionHandler;

  /**
   * OrchestrationProviderBase constructor.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   Orchestration config.
   * @param \UniversityOfAdelaide\OpenShift\Client $client
   *   The Openshift Client.
   * @param \Drupal\shp_custom\Service\StringGenerator $string_generator
   *   Shepherd custom string generator.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   * @param \Drupal\shp_orchestration\ExceptionHandler $exceptionHandler
   *   The exception handler service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ImmutableConfig $config, OpenShiftClient $client, StringGenerator $string_generator, MessengerInterface $messenger, ExceptionHandler $exceptionHandler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->client = $client;
    $this->stringGenerator = $string_generator;
    $this->messenger = $messenger;
    $this->config = $config;
    $this->exceptionHandler = $exceptionHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')->get('shp_orchestration.settings'),
      $container->get('shp_orchestration.client'),
      $container->get('shp_custom.string_generator'),
      $container->get('messenger'),
      $container->get('shp_orchestration.exception_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function createdProject(string $name, string $builder_image, string $source_repo, string $source_ref = 'master', string $source_secret = NULL, array $environment_variables = []) {
    $sanitised_project_name = self::sanitise($name);
    $sanitised_source_ref = self::sanitise($source_ref);
    $image_stream_tag = $sanitised_project_name . ':' . $sanitised_source_ref;
    $build_config_name = $sanitised_project_name . '-' . $sanitised_source_ref;

    $formatted_env_vars = $this->formatEnvVars($environment_variables);

    try {
      $this->setSiteConfig(0);

      $image_stream = $this->client->generateImageStreamConfig($sanitised_project_name);
      $this->client->createImageStream($image_stream);
      $this->createBuildConfig($build_config_name, $source_ref, $source_repo, $builder_image, $source_secret, $image_stream_tag, $formatted_env_vars);
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function updatedProject(string $name, string $builder_image, string $source_repo, string $source_ref = 'master', string $source_secret = NULL, array $environment_variables = []) {
    $sanitised_name = self::sanitise($name);

    // Package config for the client.
    $build_data = [
      'git' => [
        'ref' => $source_ref,
        'uri' => $source_repo,
      ],
      'source' => [
        'type' => 'DockerImage',
        'name' => $builder_image,
      ],
    ];

    try {
      $this->setSiteConfig(0);

      $this->client->updateBuildConfig(
        $sanitised_name . '-' . $source_ref,
        $source_secret,
        $sanitised_name . ':' . $source_ref,
        $build_data
      );
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function deletedProject($name) {
    // @todo Implement deletedProject() method.
  }

  /**
   * Create a build config in OpenShift.
   *
   * @param string $build_config_name
   *   Build config name.
   * @param string $source_ref
   *   Source ref.
   * @param string $source_repo
   *   Source repo.
   * @param string $builder_image
   *   Builder image.
   * @param string $source_secret
   *   Source secret.
   * @param string $image_stream_tag
   *   Image stream tag.
   * @param array $formatted_env_vars
   *   Formatted env vars.
   *
   * @return bool
   *   Created or already exists = TRUE. Fail = FALSE.
   */
  protected function createBuildConfig(string $build_config_name, string $source_ref, string $source_repo, string $builder_image, string $source_secret, string $image_stream_tag, array $formatted_env_vars) {
    // Create build config if it doesn't exist.
    $buildConfig = $this->client->getBuildConfig($build_config_name);
    if ($buildConfig['code'] === 404) {
      $build_data = $this->formatBuildData($source_ref, $source_repo, $builder_image, $formatted_env_vars);

      $build_config = $this->client->generateBuildConfig(
        $build_config_name,
        $source_secret,
        $image_stream_tag,
        $build_data
      );

      try {
        $this->client->createBuildConfig($build_config);
      }
      catch (ClientException $e) {
        $this->exceptionHandler->handleClientException($e);
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function createdEnvironment(
    string $project_name,
    string $short_name,
    int $site_id,
    int $environment_id,
    string $environment_url,
    string $builder_image,
    string $source_repo,
    string $source_ref = 'master',
    string $source_secret = NULL,
    string $storage_class = '',
    bool $update_on_image_change = FALSE,
    bool $cron_suspended = FALSE,
    array $environment_variables = [],
    array $secrets = [],
    array $probes = [],
    array $cron_jobs = [],
    string $backup_schedule = '',
    int $backup_retention = 0,
    Route $route = NULL
  ) {
    // @todo Refactor this. _The complexity is too damn high!_
    $sanitised_project_name = self::sanitise($project_name);
    $sanitised_source_ref = self::sanitise($source_ref);
    $deployment_name = self::generateDeploymentName($environment_id);
    $image_stream_tag = $sanitised_project_name . ':' . $sanitised_source_ref;
    $build_config_name = $sanitised_project_name . '-' . $sanitised_source_ref;
    $formatted_env_vars = $this->formatEnvVars($environment_variables, $deployment_name);

    $this->setSiteConfig($site_id);

    // Tell, don't ask (to create a build config).
    $this->createBuildConfig($build_config_name, $source_ref, $source_repo, $builder_image, $source_secret, $image_stream_tag, $formatted_env_vars);

    // Setup all the volumes that might be mounted.
    $volumes = $this->generateVolumeData($project_name, $deployment_name, $secrets);
    if (!$this->setupVolumes($project_name, $deployment_name, $storage_class)) {
      return FALSE;
    }

    $deploy_data = $this->formatDeployData(
      $deployment_name,
      $formatted_env_vars,
      $environment_url,
      $site_id,
      $environment_id
    );

    $deployment_config = $this->client->generateDeploymentConfig(
      $deployment_name,
      $image_stream_tag,
      $sanitised_project_name,
      $update_on_image_change,
      $volumes,
      $deploy_data,
      $probes
    );

    try {
      $this->client->createDeploymentConfig($deployment_config);
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }

    // Get the volumes and include the backups this time.
    $cron_volumes = $this->generateVolumeData($project_name, $deployment_name, $secrets, TRUE);

    $image = $this->getImageStreamImage($sanitised_project_name, $sanitised_source_ref);
    if ($image) {
      $this->createCronJobs(
        $deployment_name,
        $cron_suspended,
        $cron_jobs,
        $image,
        $cron_volumes,
        $deploy_data
      );
      $this->client->instantiateDeploymentConfig($deployment_name);
    }
    else {
      $this->messenger->addStatus(t('Image unavailable, deployment and cron jobs cannot be completed.'));
    }

    // @todo make port a var and great .. so great .. yuge!
    $port = 8080;
    try {
      $this->client->createService($deployment_name, $deployment_name, $port, $port, $deployment_name);
      $this->client->createRoute($route);
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }

    if ($backup_schedule) {
      $this->environmentScheduleBackupCreate($site_id, $environment_id, $backup_schedule, $backup_retention);
    }

    return TRUE;
  }

  /**
   * Returns the specific image definition from an image stream.
   *
   * @param string $sanitised_project_name
   *   The project name.
   * @param string $sanitised_source_ref
   *   The source ref.
   *
   * @return array|bool
   *   Image definition if exists otherwise FALSE.
   *
   * @throws \UniversityOfAdelaide\OpenShift\ClientException
   */
  protected function getImageStreamImage(string $sanitised_project_name, string $sanitised_source_ref) {
    // Retrieve image stream that will be used for this site. There is only a
    // tiny chance it will be different to the deployment config image.
    $image_stream = $this->client->getImageStream($sanitised_project_name);
    if (is_array($image_stream) && isset($image_stream['status']['tags'])) {
      // Look through the image stream tags to find the one being deployed.
      foreach ($image_stream['status']['tags'] as $index => $images) {
        if ($images['tag'] === $sanitised_source_ref) {
          // Got one! [0] is the most recently created image.
          return $images['items'][0]['dockerImageReference'] ?? FALSE;
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function updatedEnvironment(
    string $project_name,
    string $short_name,
    string $site_id,
    string $environment_id,
    string $environment_url,
    string $builder_image,
    string $source_repo,
    string $source_ref = 'master',
    string $source_secret = NULL,
    string $storage_class = '',
    bool $update_on_image_change = FALSE,
    bool $cron_suspended = FALSE,
    array $environment_variables = [],
    array $secrets = [],
    array $probes = [],
    array $cron_jobs = [],
    string $backup_schedule = '',
    int $backup_retention = 0,
    Route $route = NULL,
    Hpa $hpa = NULL
  ) {
    // @todo Refactor this too. Not DRY enough.
    $deployment_name = self::generateDeploymentName($environment_id);
    $deployment_config = $this->client->getDeploymentConfig($deployment_name);
    $formatted_env_vars = $this->formatEnvVars($environment_variables, $deployment_name);

    $this->setSiteConfig($site_id);

    if (!$this->setupVolumes($project_name, $deployment_name, $storage_class)) {
      return FALSE;
    }

    $deploy_data = $this->formatDeployData(
      $deployment_name,
      $formatted_env_vars,
      $environment_url,
      $site_id,
      $environment_id
    );

    $this->client->updateDeploymentConfig($deployment_name, $deployment_config, [
      'spec' => [
        'template' => [
          'spec' => [
            'containers' => [
              0 => [
                'env' => $deploy_data['env_vars'],
                'resources' => [
                  'limits' => [
                    'cpu' => $deploy_data['cpu_limit'],
                    'memory' => $deploy_data['memory_limit'],
                  ],
                  'requests' => [
                    'cpu' => $deploy_data['cpu_request'],
                    'memory' => $deploy_data['memory_request'],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ]);
    // Update the HPA only if there is one.
    if ($this->client->getHpa($deployment_name)) {
      $hpa->setName($deployment_name);
      try {
        $this->client->updateHpa($hpa);
      }
      catch (ClientException $e) {
        $this->exceptionHandler->handleClientException($e);
        return FALSE;
      }
    }

    // Remove all the existing cron jobs.
    $this->client->deleteCronJob('', 'app=' . $deployment_name);

    // Re-create all the cron jobs.
    $volumes = $this->generateVolumeData($project_name, $deployment_name, $secrets, TRUE);

    // Retrieve image to use for cron jobs, dont try and create if no image yet.
    if ($image = $deployment_config['spec']['template']['spec']['containers'][0]['image'] ?? FALSE) {
      $this->createCronJobs(
        $deployment_name,
        $cron_suspended,
        $cron_jobs,
        $image,
        $volumes,
        $deploy_data
      );
    }

    // Add/remove the backup schedule as determined by environment type.
    if ($backup_schedule) {
      $this->environmentScheduleBackupUpdate($site_id, $environment_id, $backup_schedule, $backup_retention);
    }
    else {
      $this->environmentScheduleBackupDelete($environment_id);
    }

    return TRUE;
  }

  /**
   * Helper function to set the namespace and token before calling the api.
   *
   * @param int|null $site_id
   *   The site which dictates which service account quota will be used.
   *
   * @throws \UniversityOfAdelaide\OpenShift\ClientException
   */
  private function setSiteConfig(int $site_id = NULL) {
    // If called with no parameters, set the defaults to shepherd.
    $this->client->setToken($this->config->get('connection.token'));
    $this->client->setNamespace($this->config->get('connection.namespace'));

    // Return now if no site id passed.
    if (!$site_id) {
      return;
    }

    // Retrieve the token first from the shepherd namespace.
    $this->client->setToken($this->getSiteToken($site_id));

    // Then we can change to the sites namespace.
    $this->client->setNamespace($this->getSiteNamespace($site_id));
  }

  /**
   * {@inheritdoc}
   */
  public function deletedEnvironment(
    string $project_name,
    string $short_name,
    int $site_id,
    int $environment_id
  ) {
    $deployment_name = self::generateDeploymentName($environment_id);

    $this->setSiteConfig($site_id);

    try {
      // Scale the pods to zero, then delete the pod creators.
      // @todo placing the logic here .. as its not clear what level of logic we should place in client.
      $deploymentConfigs = $this->client->getDeploymentConfigs('app=' . $deployment_name);
      foreach ($deploymentConfigs['items'] as $deploymentConfig) {
        $this->client->updateDeploymentConfig($deploymentConfig['metadata']['name'], $deploymentConfig, [
          'apiVersion' => 'apps.openshift.io/v1',
          'kind' => 'DeploymentConfig',
          'spec' => [
            'replicas' => 0,
          ],
        ]);
      }
      $this->client->deleteCronJob('', 'app=' . $deployment_name);
      $this->client->deleteJob('', 'app=' . $deployment_name);
      $this->client->deleteRoute($deployment_name);
      $this->client->deleteService($deployment_name);
      $this->client->deleteDeploymentConfig($deployment_name);
      $this->client->deleteHpa($deployment_name);
      $this->client->deleteReplicationControllers('', 'app=' . $deployment_name);

      // Now the things not in the typically visible ui.
      $this->client->deletePersistentVolumeClaim($deployment_name . '-shared');
      $this->client->deleteSecret($deployment_name);
      $scheduleName = self::generateScheduleName($deployment_name);
      if ($this->client->getSchedule($scheduleName)) {
        $this->client->deleteSchedule($scheduleName);
      }
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function archivedEnvironment(
    int $environment_id
  ) {
    // @todo - This is all broken, input is an int, not an object, remove?
    $site = Node::load($environment_id->field_shp_site->target_id);
    $project = Node::load($site->field_shp_project->target_id);

    $this->deletedEnvironment(
      $project->title->value,
      $site->field_shp_short_name->value,
      $environment_id
    );
  }

  /**
   * {@inheritdoc}
   */
  public function promotedEnvironment(
    string $project_name,
    string $short_name,
    int $site_id,
    int $environment_id,
    string $source_ref = 'master',
    bool $clear_cache = TRUE,
    Route $route = NULL,
    Hpa $hpa = NULL
  ) {

    $this->setSiteConfig($site_id);

    $site_deployment_name = self::generateDeploymentName($site_id);

    $environment_deployment_name = self::generateDeploymentName($environment_id);

    // @todo remove the hardcoded ports.
    $port = 8080;

    if (!$this->client->getService($site_deployment_name)) {
      $this->client->createService($site_deployment_name, $site_deployment_name, $port, $port, $site_deployment_name);
    }

    if (!$this->client->getRoute($site_deployment_name)) {
      $this->client->createRoute($route);
    }

    if ($hpa && !$this->client->getHpa($environment_deployment_name)) {
      $hpa->setName($environment_deployment_name);
      try {
        $this->client->createHpa($hpa);
      }
      catch (ClientException $e) {
        $this->exceptionHandler->handleClientException($e);
        return FALSE;
      }
    }

    $result = $this->client->updateService($site_deployment_name, $environment_deployment_name);
    if ($result && $clear_cache) {
      // @todo Remove drush call, it relates to a project type rather than all projects.
      $this->executeJob(
        $project_name,
        $short_name,
        $environment_id,
        $source_ref,
        "drush -r /code/web cr"
      );
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function createdSite(
    string $project_name,
    string $short_name,
    int $site_id,
    string $domain_name,
    string $path
  ) {
    // Set the auth to be the site token.
    $this->setSiteConfig($site_id);

    // Now Create a project/namespace for the new site.
    $this->client->createProjectRequest($this->buildProjectName($short_name));

    // @todo This works for local dev, but what to do here eh?
    $this->createRoleBinding('developer', 'admin');
  }

  /**
   * Construct a unique role name but with some meaningful aspects.
   *
   * @param string $user
   *   The user being granted the role.
   * @param string $role
   *   The role being granted.
   */
  public function createRoleBinding(string $user, string $role) {
    $roleBindingName = implode('-', [
      $user, $role,
      $this->stringGenerator->generateRandomString(5),
    ]);

    $this->client->createRoleBinding($user, $role, $roleBindingName);
  }

  /**
   * {@inheritdoc}
   */
  public function updatedSite() {
    // @todo Implement updateSite() method.
  }

  /**
   * {@inheritdoc}
   */
  public function deletedSite(
    string $project_name,
    string $short_name,
    int $site_id
  ) {
    $this->setSiteConfig($site_id);

    $deployment_name = self::generateDeploymentName($site_id);

    $this->client->deleteService($deployment_name);
    $this->client->deleteRoute($deployment_name);
  }

  /**
   * {@inheritdoc}
   */
  public function getBackup(string $name) {
    try {
      return $this->client->getBackup($name);
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateBackup(Backup $backup) {
    try {
      return $this->client->updateBackup($backup);
    }
    catch (ClientException $e) {
      $this->handleClientException($e);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteBackup(string $name) {
    try {
      return $this->client->deleteBackup($name);
    }
    catch (ClientException $e) {
      $this->handleClientException($e);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function backupEnvironment(string $site_id, string $environment_id, string $friendly_name = '') {
    $deployment_name = self::generateDeploymentName($environment_id);
    /** @var \UniversityOfAdelaide\OpenShift\Objects\Backups\Backup $backup */
    $backup = Backup::create()
      ->setVolumes(['shared' => self::generateSharedPvcName($deployment_name)])
      ->addDatabase($this->generateDatabaseFromDeploymentName($deployment_name))
      ->setLabel(Label::create('site', $site_id))
      ->setLabel(Label::create('environment', $environment_id))
      ->setLabel(Label::create(Backup::MANUAL_LABEL, TRUE))
      ->setName(sprintf('%s-backup-%s', $deployment_name, date('YmdHis')));
    if (!empty($friendly_name)) {
      $backup->setAnnotation(Backup::FRIENDLY_NAME_ANNOTATION, $friendly_name);
    }
    try {
      return $this->client->createBackup($backup);
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }
  }

  /**
   * Generates a database object used by backup/restores from a deployment.
   *
   * @param string $deployment_name
   *   The deployment name.
   *
   * @return \UniversityOfAdelaide\OpenShift\Objects\Backups\Database
   *   A database object.
   */
  private function generateDatabaseFromDeploymentName(string $deployment_name) {
    return (new Database())
      ->setId('default')
      ->setSecretName($deployment_name)
      ->setSecretKeys([
        $this::KeyMySQLHostname => 'DATABASE_HOST',
        $this::KeyMySQLDatabase => 'DATABASE_NAME',
        $this::KeyMySQLPort => 'DATABASE_PORT',
        $this::KeyMySQLUsername => 'DATABASE_USER',
        $this::KeyMySQLPassword => 'DATABASE_PASSWORD',
      ]);
  }

  /**
   * {@inheritdoc}
   */
  public function environmentScheduleBackupCreate(string $site_id, string $environment_id, string $schedule, int $retention) {
    $deployment_name = self::generateDeploymentName($environment_id);
    /** @var \UniversityOfAdelaide\OpenShift\Objects\Backups\ScheduledBackup $schedule */
    $schedule = ScheduledBackup::create()
      ->setVolumes(['shared' => self::generateSharedPvcName($deployment_name)])
      ->addDatabase($this->generateDatabaseFromDeploymentName($deployment_name))
      ->setLabel(Label::create('site', $site_id))
      ->setLabel(Label::create('environment', $environment_id))
      ->setName(self::generateScheduleName($deployment_name))
      ->setSchedule($schedule)
      ->setRetention($retention);
    try {
      return $this->client->createSchedule($schedule);
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function environmentScheduleBackupUpdate(string $site_id, string $environment_id, string $schedule, int $retention) {
    $schedule_name = self::generateScheduleName(self::generateDeploymentName($environment_id));
    try {
      $schedule_obj = $this->client->getSchedule($schedule_name);
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }
    // If there's no schedule, create one.
    if (!$schedule_obj) {
      return $this->environmentScheduleBackupCreate($site_id, $environment_id, $schedule, $retention);
    }

    // No point updating if the schedules are the same!
    if ($schedule_obj->getSchedule() === $schedule) {
      return $schedule_obj;
    }

    // Existing schedule, update it.
    $schedule_obj->setSchedule($schedule);
    $schedule_obj->setRetention($retention);
    try {
      return $this->client->updateSchedule($schedule_obj);
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function environmentScheduleBackupDelete(string $environment_id) {
    $schedule_name = self::generateScheduleName(self::generateDeploymentName($environment_id));
    try {
      if ($this->client->getSchedule($schedule_name)) {
        $this->client->deleteSchedule($schedule_name);
      }
      return TRUE;
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }
  }

  /**
   * Get backups for a given label with exception handling.
   *
   * @param \UniversityOfAdelaide\OpenShift\Objects\Label $label
   *   The label selector to apply.
   *
   * @return bool|object|\UniversityOfAdelaide\OpenShift\Objects\Backups\BackupList
   *   A backup list, or false.
   */
  protected function getBackupsByLabel(Label $label) {
    try {
      return $this->client->listBackup($label);
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getBackupsForSite(string $site_id) {
    return $this->getBackupsByLabel(Label::create('site', $site_id));
  }

  /**
   * {@inheritdoc}
   */
  public function getBackupsForEnvironment(string $environment_id) {
    return $this->getBackupsByLabel(Label::create('environment', $environment_id));
  }

  /**
   * {@inheritdoc}
   */
  public function restoreEnvironment(string $backup_name, string $site_id, string $environment_id) {
    $deployment_name = self::generateDeploymentName($environment_id);
    /** @var \UniversityOfAdelaide\OpenShift\Objects\Backups\Restore $restore */
    $restore = Restore::create()
      ->setVolumes(['shared' => self::generateSharedPvcName($deployment_name)])
      ->addDatabase($this->generateDatabaseFromDeploymentName($deployment_name))
      ->setName(sprintf('%s-restore-%s', $deployment_name, date('YmdHis')))
      ->setBackupName($backup_name)
      ->setLabel(Label::create('site', $site_id))
      ->setLabel(Label::create('environment', $environment_id));
    try {
      return $this->client->createRestore($restore);
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRestoresForSite(string $site_id) {
    try {
      return $this->client->listRestore(Label::create('site', $site_id));
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function syncEnvironments(string $site_id, string $from_env, string $to_env) {
    $backupDeploymentName = self::generateDeploymentName($from_env);
    $restoreDeploymentName = self::generateDeploymentName($to_env);
    /** @var \UniversityOfAdelaide\OpenShift\Objects\Backups\Sync $sync */
    $sync = Sync::create()
      ->setSite($site_id)
      ->setBackupEnv($from_env)
      ->setRestoreEnv($to_env)
      ->setBackupVolumes(['shared' => self::generateSharedPvcName($backupDeploymentName)])
      ->setRestoreVolumes(['shared' => self::generateSharedPvcName($restoreDeploymentName)])
      ->setBackupDatabases([$this->generateDatabaseFromDeploymentName($backupDeploymentName)])
      ->setRestoreDatabases([$this->generateDatabaseFromDeploymentName($restoreDeploymentName)])
      ->setName(sprintf('%s-%s-%s', $backupDeploymentName, $restoreDeploymentName, date('YmdHis')))
      ->setLabel(Label::create('site', $site_id));
    try {
      return $this->client->createSync($sync);
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSyncs() {
    try {
      return $this->client->listSync();
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSyncsForSite(string $site_id) {
    try {
      return $this->client->listSync(Label::create('site', $site_id));
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function executeJob(
    string $project_name,
    string $short_name,
    string $environment_id,
    string $source_ref = 'master',
    string $commands = ''
  ) {
    $sanitised_project_name = self::sanitise($project_name);
    $sanitised_source_ref = self::sanitise($source_ref);
    $deployment_name = self::generateDeploymentName($environment_id);

    // Retrieve existing deployment details to use where possible.
    $deployment_config = $this->client->getDeploymentConfig($deployment_name);

    // The image may either be not set, or set and blank. If either of those,
    // bail, rather than possibly use an incorrect image.
    $image = $deployment_config['spec']['template']['spec']['containers'][0]['image'] ?? FALSE;
    if (!$image || empty(trim($image))) {
      return [];
    }

    $volumes = $this->generateVolumeData($project_name, $deployment_name, [], TRUE);

    $deploy_data = $this->formatDeployData(
      $deployment_name,
      $deployment_config['spec']['template']['spec']['containers'][0]['env'],
      $deployment_config['metadata']['annotations']['shepherdUrl'],
      $deployment_config['metadata']['labels']['site_id'],
      $environment_id
    );

    $args_array = [
      '/bin/sh',
      '-c',
      $commands,
    ];
    try {
      $response_body = $this->client->createJob(
        $deployment_name . '-' . $this->stringGenerator->generateRandomString(5),
        $image,
        $args_array,
        $volumes,
        $deploy_data
      );
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }
    return $response_body;
  }

  /**
   * Fetch the job from the provider.
   *
   * @param string $name
   *   The job name.
   *
   * @return array|bool
   *   The job, else false.
   */
  public function getJob(string $name) {
    return $this->client->getJob($name);
  }

  /**
   * {@inheritdoc}
   */
  public function getSecret(int $site_id, string $name, string $key = NULL) {
    $this->setSiteConfig($site_id);

    try {
      $secret = $this->client->getSecret($name);
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }
    if (is_array($secret) && array_key_exists('data', $secret)) {
      if ($key) {
        return array_key_exists($key, $secret['data']) ? base64_decode($secret['data'][$key]) : FALSE;
      }
      return array_map('base64_decode', $secret['data']);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function createSecret(int $site_id, string $name, array $data) {
    $this->setSiteConfig($site_id);

    try {
      return $this->client->createSecret($name, $data);
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateSecret(int $site_id, string $name, array $data) {
    $this->setSiteConfig($site_id);

    try {
      return $this->client->updateSecret($name, $data);
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSiteEnvironmentsStatus(string $site_id) {
    $this->setSiteConfig($site_id);
    try {
      $deployment_configs = $this->client->getDeploymentConfigs('site_id=' . $site_id);
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }
    $environments_status = [];
    foreach ($deployment_configs['items'] as $deployment_config) {
      // Search through the conditions for a key of type 'available'
      // This defines if the deployment config is effectively running or not.
      $environments_status[] = $this->extractDeploymentConfigStatus($deployment_config);
    }

    return $environments_status;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironmentStatus(string $project_name, string $short_name, string $environment_id) {

    $environment = Node::load($environment_id);
    $this->setSiteConfig($environment->field_shp_site->entity->id());

    $deployment_name = self::generateDeploymentName($environment_id);

    try {
      $deployment_config = $this->client->getDeploymentConfig($deployment_name);
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }

    return $deployment_config ? $this->extractDeploymentConfigStatus($deployment_config) : FALSE;
  }

  /**
   * Pull the status from a deployment config.
   *
   * @param array $deployment_config
   *   Deployment config.
   *
   * @return array
   *   Extracted array that contains the status, time and number of pods.
   */
  protected function extractDeploymentConfigStatus(array $deployment_config) {
    $environment_status = [];
    foreach ($deployment_config['status']['conditions'] as $condition) {
      if (strtolower($condition['type']) === 'available') {
        $environment_status = [
          'running' => $condition['status'] === 'True',
          'time' => $condition['lastUpdateTime'],
          'available_pods' => $deployment_config['status']['availableReplicas'],
        ];
        break;
      }
    }
    return $environment_status;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironmentUrl(string $project_name, string $short_name, string $environment_id) {
    $deployment_name = self::generateDeploymentName($environment_id);

    try {
      $route = $this->client->getRoute($deployment_name);
      return Url::fromUri('//' . $route['spec']['host'] . (array_key_exists('path', $route['spec']) ? $route['spec']['path'] : '/'));
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }
    catch (\InvalidArgumentException $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTerminalUrl(string $project_name, string $short_name, string $environment_id) {
    $deployment_name = self::generateDeploymentName($environment_id);

    try {
      $pods = $this->client->getPod('', 'app=' . $deployment_name . ',environment_id=' . $environment_id);
      // If there are no running pods, return now.
      if (!count($pods['items'])) {
        return FALSE;
      }

      // Determine the link to the correct pod.
      foreach ($pods['items'] as $index => $details) {
        // Return the first running, non-job container.
        if ($this->isWebPod($details)) {
          $pod_name = $pods['items'][0]['metadata']['name'];
          return $this->generateOpenShiftPodUrl($pod_name, 'terminal');
        }
      }
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }
    catch (\InvalidArgumentException $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLogUrl(string $project_name, string $short_name, string $environment_id) {
    $deployment_name = self::generateDeploymentName($environment_id);

    try {
      $pods = $this->client->getPod('', 'app=' . $deployment_name . ',environment_id=' . $environment_id);
      // If there are no running pods, return now.
      if (!count($pods['items'])) {
        return FALSE;
      }

      // Determine the link to the correct pod.
      foreach ($pods['items'] as $index => $details) {
        // Return the first running, non-job container.
        if ($this->isWebPod($details)) {
          $pod_name = $pods['items'][0]['metadata']['name'];
          return $this->generateOpenShiftPodUrl($pod_name, 'logs');
        }
      }
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }
    catch (\InvalidArgumentException $e) {
      return FALSE;
    }
  }

  /**
   * Helper function to confirm if requested pod is a web pod.
   *
   * @param string $pod
   *   Pod name.
   *
   * @return bool
   *   True if web pod, false otherwise.
   */
  protected function isWebPod($pod) {
    return !isset($pod['metadata']['job-name']) &&
      $pod['status']['phase'] === 'Running' &&
      !strpos($pod['metadata']['name'], 'redis');
  }

  /**
   * Generates a url to a specific pod and view in OpenShift.
   *
   * @param string $pod_name
   *   Pod name.
   * @param string $view
   *   View/tab to display.
   *
   * @return string
   *   Url.
   */
  protected function generateOpenShiftPodUrl(string $pod_name, string $view) {
    $endpoint = $this->config->get('connection.endpoint');

    /* @todo This will need to change */
    $namespace = $this->config->get('connection.namespace');

    return Url::fromUri($endpoint . '/console/project/' . $namespace . '/browse/pods/' . $pod_name, [
      'query' => [
        'tab' => $view,
      ],
      'attributes' => [
        'target' => '_blank',
      ],
    ]);
  }

  /**
   * Format an array of environment variables ready to pass to OpenShift.
   *
   * @todo move this into the client?
   *
   * @param array $environment_variables
   *   An array of environment variables to be set for the pod.
   * @param string $deployment_name
   *   The deployment name.
   *
   * @return array
   *   The env var config array.
   */
  protected function formatEnvVars(array $environment_variables, string $deployment_name = '') {
    $formatted_env_vars = [];

    foreach ($environment_variables as $name => $value) {
      if (is_string($value)) {
        // Plain environment variable.
        $formatted_env_vars[] = [
          'name' => $name,
          'value' => $value,
        ];
      }
      elseif (is_array($value) && array_key_exists('secret', $value)) {
        // Sourced from secret.
        $formatted_env_vars[] = [
          'name' => $name,
          'valueFrom' => [
            'secretKeyRef' => [
              // If secret is '_default' use the deployment config secret.
              'name' => $value['secret'] === '_default' ? $deployment_name : $value['secret'],
              'key' => $value['secret_key'],
            ],
          ],
        ];
      }
    }

    return $formatted_env_vars;
  }

  /**
   * Format an array of deployment data ready to pass to OpenShift.
   *
   * @param string $name
   *   The name of the deployment config.
   * @param array $formatted_env_vars
   *   An array of key => value env var pairs.
   * @param string $environment_url
   *   The url of the environment being created.
   * @param int $site_id
   *   The ID of the site the environment represents.
   * @param int $environment_id
   *   The ID of the environment being created.
   *
   * @return array
   *   The deployment config array.
   */
  protected function formatDeployData(string $name, array $formatted_env_vars, string $environment_url, int $site_id, int $environment_id) {
    $deploy_data = [
      'containerPort' => 8080,
      'cpu_limit' => '200m',
      'cpu_request' => '100m',
      'memory_limit' => '512Mi',
      'memory_request' => '256Mi',
      'env_vars' => $formatted_env_vars,
      'annotations' => [
        'shepherdUrl' => $environment_url,
      ],
      'labels' => [
        'site_id' => (string) $site_id,
        'environment_id' => (string) $environment_id,
        'app' => $name,
        'deploymentconfig' => $name,
      ],
    ];

    // If set, add uid and gid from config to deploy data.
    if (strlen($this->config->get('connection.uid')) > 0) {
      $deploy_data['uid'] = $this->config->get('connection.uid');
      if (strlen($this->config->get('connection.gid')) > 0) {
        $deploy_data['gid'] = $this->config->get('connection.gid');
      }
    }

    $request_limits = $this->generateRequestLimits($environment_id);
    $deploy_data = array_merge($deploy_data, $request_limits);

    return $deploy_data;
  }

  /**
   * Generate request limits.
   *
   * @param int $environment_id
   *   The ID of the environment being deployed.
   *
   * @return array
   *   Array with cpu & memory request & limits.
   */
  protected function generateRequestLimits(int $environment_id) {
    $request_limits = [];

    /** @var \Drupal\node\Entity\Node $environment */
    $environment = Node::load($environment_id);
    /** @var \Drupal\node\Entity\Node $site */
    $site = $environment->field_shp_site->entity;
    /** @var \Drupal\node\Entity\Node $project */
    $project = $site->field_shp_project->entity;

    $fields = [
      'field_shp_cpu_request'    => 'cpu_request',
      'field_shp_cpu_limit'      => 'cpu_limit',
      'field_shp_memory_request' => 'memory_request',
      'field_shp_memory_limit'   => 'memory_limit',
    ];

    foreach ($fields as $field => $client_field) {
      if (!$environment->{$field}->isEmpty()) {
        $request_limits[$client_field] = $environment->{$field}->value;
        continue;
      }

      if (!$project->{$field}->isEmpty()) {
        $request_limits[$client_field] = $project->{$field}->value;
        continue;
      }

    }

    return $request_limits;
  }

  /**
   * Format an array of build data ready to pass to OpenShift.
   *
   * @todo move this into the client?
   *
   * @param string $source_ref
   *   The source tag/branch/commit.
   * @param string $source_repo
   *   The source repository.
   * @param string $builder_image
   *   The builder image.
   * @param array $formatted_env_vars
   *   Environment variables.
   *
   * @return array
   *   Build data.
   */
  protected function formatBuildData(string $source_ref, string $source_repo, string $builder_image, array $formatted_env_vars = []) {
    // Package config for the client.
    return [
      'git' => [
        'ref' => $source_ref,
        'uri' => $source_repo,
      ],
      'source' => [
        'type' => 'DockerImage',
        'name' => $builder_image,
      ],
      'env_vars' => $formatted_env_vars,
    ];
  }

  /**
   * Attempt to create PVC's for the OpenShift deployment.
   *
   * PVC's that already exist will not be created/updated.
   *
   * @todo move this into the client?
   * @todo make storage size configurable
   *
   * @param string $project_name
   *   The name of the project being deployed.
   * @param string $deployment_name
   *   The name of the deployment being created.
   * @param string $storage_class
   *   Optional storage class name.
   *
   * @return bool
   *   Whether setting up the PVC's succeeded or not.
   */
  protected function setupVolumes(string $project_name, string $deployment_name, $storage_class = '') {
    $shared_pvc_name = self::generateSharedPvcName($deployment_name);
    $backup_pvc_name = self::generateBackupPvcName($project_name);

    // Setup PVC's for the ones that are NOT secrets.
    try {
      if (!$this->client->getPersistentVolumeClaim($shared_pvc_name)) {
        $this->client->createPersistentVolumeClaim(
          $shared_pvc_name,
          'ReadWriteMany',
          '5Gi',
          $deployment_name,
          $storage_class
        );
      }
      // Even though only job pods have access, we need to create the claim.
      if (!$this->client->getPersistentVolumeClaim($backup_pvc_name)) {
        $this->client->createPersistentVolumeClaim(
          $backup_pvc_name,
          'ReadWriteMany',
          '5Gi',
          $deployment_name,
          $storage_class
        );
      }
    }
    catch (ClientException $e) {
      $this->exceptionHandler->handleClientException($e);
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Generate the name of the shared PVC from a deployment name.
   *
   * @param string $deployment_name
   *   A deployment name.
   *
   * @return string
   *   The shared pvc name.
   */
  protected static function generateSharedPvcName(string $deployment_name) {
    return $deployment_name . '-shared';
  }

  /**
   * Generate the name of the backup PVC from a project name.
   *
   * @param string $project_name
   *   A project name.
   *
   * @return string
   *   The backup pvc name.
   */
  protected static function generateBackupPvcName(string $project_name) {
    return self::sanitise($project_name) . '-backup';
  }

  /**
   * Generates the volume data for deployment configuration.
   *
   * @param string $project_name
   *   The name of the project in OpenShift.
   * @param string $deployment_name
   *   The name of the deployment.
   * @param array $secrets
   *   Optional secrets to attach.
   * @param bool $mount_backup
   *   Whether to mount the backup volume (for jobs).
   *
   * @return array
   *   Volume data.
   *
   * @throws \UniversityOfAdelaide\OpenShift\ClientException
   */
  protected function generateVolumeData(string $project_name, string $deployment_name, array $secrets = [], $mount_backup = FALSE) {
    $volumes = [
      'shared' => [
        'type' => 'pvc',
        'name' => self::generateSharedPvcName($deployment_name),
        'path' => '/shared',
      ],
      'local' => [
        'type' => 'empty',
        'name' => 'local',
        'path' => '/local',
      ],
    ];

    if ($mount_backup) {
      $volumes['backup'] = [
        'type' => 'pvc',
        'name' => self::generateBackupPvcName($project_name),
        'path' => '/backup',
      ];
    }

    // If a secret with the same name as the deployment exists, volume it in.
    if ($this->client->getSecret($deployment_name)) {
      // @todo Consider allowing parameters for secret volume path. Is there a convention?
      $volumes['secret'] = [
        'type' => 'secret',
        'name' => $deployment_name . '-secret',
        'path' => '/etc/secret',
        'secret' => $deployment_name,
      ];
    }

    // Append project and environment specific secrets to the list of volumes.
    if (count($secrets)) {
      if (array_key_exists('environment', $secrets)) {
        $volumes['secret-environment'] = [
          'name' => 'secret-environment',
          'path' => '/etc/secret-environment',
          'type' => 'secret',
          'secret' => $secrets['environment'],
        ];
      }
      if (array_key_exists('project', $secrets)) {
        $volumes['secret-project'] = [
          'name' => 'secret-project',
          'path' => '/etc/secret-project',
          'type' => 'secret',
          'secret' => $secrets['project'],
        ];
      }
    }

    return $volumes;
  }

  /**
   * Create cron jobs.
   *
   * @param string $deployment_name
   *   Deployment identifier.
   * @param bool $cron_suspended
   *   Is cron suspended?
   * @param array $cron_jobs
   *   The jobs to run.
   * @param string $image_stream
   *   Image stream.
   * @param array $volumes
   *   Volumes to mount.
   * @param array $deploy_data
   *   Deploy data.
   *
   * @return bool
   *   True on success.
   */
  protected function createCronJobs(string $deployment_name, bool $cron_suspended, array $cron_jobs, string $image_stream, array $volumes, array $deploy_data) {
    foreach ($cron_jobs as $cron_job) {
      $args_array = [
        '/bin/sh',
        '-c',
        $cron_job['cmd'],
      ];
      try {
        $this->client->createCronJob(
          $deployment_name . '-' . $this->stringGenerator->generateRandomString(5),
          $image_stream,
          $cron_job['schedule'],
          $cron_suspended,
          $args_array,
          $volumes,
          $deploy_data
        );
      }
      catch (ClientException $e) {
        $this->exceptionHandler->handleClientException($e);
        return FALSE;
      }
    }
    return TRUE;
  }

}
