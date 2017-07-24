<?php

/**
 * @file
 * Contains \Robo\RoboFileBase.
 *
 * Implementation of class for Robo - http://robo.li/
 */

/**
 * Class RoboFile.
 */
abstract class RoboFileBase extends \Robo\Tasks {

  protected $drush_cmd;
  protected $local_user;
  protected $sudo_cmd;

  protected $drush_bin = "bin/drush";
  protected $composer_bin = "composer";

  protected $php_enable_module_command = 'phpenmod -v ALL';
  protected $php_disable_module_command = 'phpdismod -v ALL';

  protected $web_server_user = 'www-data';

  protected $application_root = "web";
  protected $file_public_path = '/shared/public';
  protected $file_private_path = '/shared/private';
  protected $services_yml = "web/sites/default/services.yml";
  protected $settings_php = "web/sites/default/settings.php";

  protected $config = [];

  protected $config_new_directory = 'config_new';
  protected $config_old_directory = 'config_old';

  /**
   * Initialize config variables and apply overrides.
   */
  public function __construct() {
    $this->drush_cmd = "$this->drush_bin -r $this->application_root";
    $this->sudo_cmd = posix_getuid() == 0 ? '' : 'sudo';
    $this->local_user = $this->getLocalUser();

    // Read config from env vars.
    $environment_config = $this->readConfigFromEnv();
    $this->config = array_merge($this->config, $environment_config);
    if (!isset($this->config['database']['username'])) {
      echo "Database config is missing.\n";
    }
  }

  /**
   * Force projects to declare which install profile to use.
   *
   * I.e. return 'some_profile'.
   */
  protected abstract function getDrupalProfile();

  /**
   * Returns known configuration from environment variables.
   *
   * Runs during the constructor; be careful not to use Robo methods.
   */
  protected function readConfigFromEnv() {
    $config = [];

    // Site.
    $config['site']['title']            = getenv('SITE_TITLE');
    $config['site']['mail']             = getenv('SITE_MAIL');
    $config['site']['admin_email']      = getenv('SITE_ADMIN_EMAIL');
    $config['site']['admin_user']       = getenv('SITE_ADMIN_USERNAME');
    $config['site']['admin_password']   = getenv('SITE_ADMIN_PASSWORD');

    // Environment.
    $config['environment']['hash_salt']       = getenv('HASH_SALT');
    $config['environment']['config_sync_dir'] = getenv('CONFIG_SYNC_DIRECTORY');

    // Databases.
    $config['database']['database']  = getenv('DATABASE_NAME');
    $config['database']['driver']    = getenv('DATABASE_DRIVER');
    $config['database']['host']      = getenv('DATABASE_HOST');
    $config['database']['port']      = getenv('DATABASE_PORT');
    $config['database']['username']  = getenv('DATABASE_USER');
    $config['database']['password']  = getenv('DATABASE_PASSWORD');
    $config['database']['namespace'] = getenv('DATABASE_NAMESPACE');
    $config['database']['prefix']    = getenv('DATABASE_PREFIX');

    // Clean up NULL values and empty arrays.
    $array_clean = function (&$item) use (&$array_clean) {
      foreach ($item as $key => $value) {
        if (is_array($value)) {
          $array_clean($item[$key]);
        }
        if (empty($item[$key]) && $value !== '0') {
          unset($item[$key]);
        }
      }
    };

    $array_clean($config);

    return $config;
  }

  /**
   * Perform a full build on the project.
   */
  public function build() {
    $start = new DateTime();
    $this->devComposerValidate();
    $this->buildMake();
    $this->buildCreateConfigSyncDir();
    $this->buildSetFilesOwner();
    $this->buildInstall();
    $this->buildSetFilesOwner();
    $this->say('Total build duration: ' . date_diff(new DateTime(), $start)->format('%im %Ss'));
  }

  /**
   * Perform a build for automated deployments.
   *
   * Don't install anything, just build the code base.
   */
  public function distributionBuild() {
    $this->devComposerValidate();
    $this->buildMake('--no-dev --optimize-autoloader');
    $this->setSitePath();
  }

  /**
   * Validate composer files and installed dependencies with strict mode off.
   */
  public function devComposerValidate() {
    $this->taskComposerValidate()
      ->withDependencies()
      ->noCheckPublish()
      ->run()
      ->stopOnFail(TRUE);
  }

  /**
   * Run composer install to fetch the application code from dependencies.
   *
   * @param string $flags
   *   Additional flags to pass to the composer install command.
   */
  public function buildMake($flags = '') {
    $successful = $this->_exec("$this->composer_bin --no-progress $flags install")->wasSuccessful();

    $this->checkFail($successful, "Composer install failed.");
  }

  /**
   * Create the config sync directory from config.
   *
   * Drupal will write a .htaccess afterwards in there.
   */
  public function buildCreateConfigSyncDir() {
    if (isset($this->config['environment']['config_sync_dir'])) {
      // Only do this if we have a config sync dir setting available.
      $this->say("Creating config sync directory.");
      $this->_exec("mkdir -p " . $this->application_root . "/" . $this->config['environment']['config_sync_dir']);
    }
  }

  /**
   * Set the owner and group of all files in the files dir to the web user.
   */
  public function buildSetFilesOwner() {
    $this->say("Setting files directory owner.");
    $this->_exec("$this->sudo_cmd chown $this->web_server_user:$this->local_user -R $this->file_public_path");
    $this->_exec("$this->sudo_cmd chown $this->web_server_user:$this->local_user -R $this->file_private_path");
    $this->setPermissions($this->file_public_path, '0775');
    $this->setPermissions($this->file_private_path, '0775');
  }

  /**
   * Clean config and files, then install Drupal and module dependencies.
   */
  public function buildInstall() {
    $this->devConfigWriteable();

    // @TODO: When is this really used? Automated builds - can be random values.
    $successful = $this->_exec("$this->drush_cmd site-install " .
      $this->getDrupalProfile() .
      " install_configure_form.enable_update_status_module=NULL" .
      " install_configure_form.enable_update_status_emails=NULL" .
      " -y" .
      " --account-mail=\"" . $this->config['site']['admin_email'] . "\"" .
      " --account-name=\"" . $this->config['site']['admin_user'] . "\"" .
      " --account-pass=\"" . $this->config['site']['admin_password'] . "\"" .
      " --site-name=\"" . $this->config['site']['title'] . "\"" .
      " --site-mail=\"" . $this->config['site']['mail'] . "\"")
      ->wasSuccessful();

    // Re-set settings.php permissions.
    $this->devConfigReadOnly();

    $this->checkFail($successful, 'drush site-install failed.');

    $this->devCacheRebuild();
  }

  /**
   * Set the RewriteBase value in .htaccess appropriate for the site.
   *
   * @TODO: Will OpenShift router deal with this for us?
   */
  public function setSitePath() {
    if (strlen($this->config['site']['path']) > 0) {
      $this->say("Setting site path.");
      $successful = $this->taskReplaceInFile("$this->application_root/.htaccess")
        ->from('# RewriteBase /drupal')
        ->to("\n  RewriteBase /" . ltrim($this->config['site']['path'], '/') . "\n")
        ->run();

      $this->checkFail($successful, "Couldn't update .htaccess file with path.");
    }
  }

  /**
   * Clean the application root in preparation for a new build.
   */
  public function buildClean() {
    $this->setPermissions("$this->application_root/sites/default", '0755');
    $this->_exec("$this->sudo_cmd rm -fR $this->application_root/core");
    $this->_exec("$this->sudo_cmd rm -fR $this->application_root/modules/contrib");
    $this->_exec("$this->sudo_cmd rm -fR $this->application_root/profiles/contrib");
    $this->_exec("$this->sudo_cmd rm -fR $this->application_root/themes/contrib");
    $this->_exec("$this->sudo_cmd rm -fR $this->application_root/sites/all");
    $this->_exec("$this->sudo_cmd rm -fR bin");
    $this->_exec("$this->sudo_cmd rm -fR vendor");
  }

  /**
   * Run all the drupal updates against a build.
   */
  public function buildApplyUpdates() {
    // Run the module updates.
    $successful = $this->_exec("$this->drush_cmd -y updatedb")->wasSuccessful();
    $this->checkFail($successful, 'running drupal updates failed.');
  }

  /**
   * Perform cache clear in the app directory.
   */
  public function devCacheRebuild() {
    $successful = $this->_exec("$this->drush_cmd cr")->wasSuccessful();

    $this->checkFail($successful, 'drush cache-rebuild failed.');
  }

  /**
   * Ask a couple of questions and then configure git.
   */
  public function devInit() {
    $this->say("Initial project setup. Adds user details to gitconfig.");
    $git_name  = $this->ask("Enter your Git name (e.g. Bob Rocks):");
    $git_email = $this->ask("Enter your Git email (e.g. bob@rocks.adelaide.edu.au):");
    $this->_exec("git config --global user.name \"$git_name\"");
    $this->_exec("git config --global user.email \"$git_email\"");

    // Automatically initialise git flow.
    $git_config = file_get_contents('.git/config');
    if (!strpos($git_config, '[gitflow')) {
      $this->taskWriteToFile(".git/config")
        ->append()
        ->text("\n[gitflow \"branch\"]\n" .
          "        master = master\n" .
          "        develop = develop\n" .
          "[gitflow \"prefix\"]\n" .
          "        feature = feature/\n" .
          "        release = release/\n" .
          "        hotfix = hotfix/\n" .
          "        support = support/\n" .
          "        versiontag = \n")
        ->run();
    }
  }

  /**
   * Install Adminer for database administration.
   */
  public function devInstallAdminer() {
    $this->taskFilesystemStack()
      ->remove("$this->application_root/adminer.php")
      ->run();

    $this->taskExec("wget -q -O adminer.php http://www.adminer.org/latest-mysql-en.php")
      ->dir($this->application_root)
      ->run();
  }

  /**
   * CLI debug enable.
   */
  public function devXdebugEnable() {
    $this->_exec("sudo $this->php_enable_module_command -s cli xdebug");
  }

  /**
   * CLI debug disable.
   */
  public function devXdebugDisable() {
    $this->_exec("sudo $this->php_disable_module_command -s cli xdebug");
  }

  /**
   * Export the current configuration to an 'old' directory.
   */
  public function configExportOld() {
    $this->configExport($this->config_old_directory);
  }

  /**
   * Export the current configuration to a 'new' directory.
   */
  public function configExportNew() {
    $this->configExport($this->config_new_directory);
  }

  /**
   * Export config to a supplied directory.
   *
   * @param string $destination
   *   The folder within the application root.
   */
  protected function configExport($destination = NULL) {
    if ($destination) {
      $this->_exec("$this->drush_cmd -y cex --destination=" . $destination);
      $this->_exec("sed -i '/^uuid: .*$/d' $this->application_root/$destination/*.yml");
    }
  }

  /**
   * Display files changed between 'config_old' and 'config_new' directories.
   *
   * @param array $opts
   *   Specify whether to show the diff output or just list them.
   *
   * @return array
   *   Diff output as an array of strings.
   */
  public function configChanges($opts = ['show|s' => FALSE]) {
    $output_style = '-qbr';
    $config_old_path = $this->application_root . '/' . $this->config_old_directory;
    $config_new_path = $this->application_root . '/' . $this->config_new_directory;

    if (isset($opts['show']) && $opts['show']) {
      $output_style = '-ubr';
    }

    $results = $this->taskExec("diff -N -I \"   - 'file:.*\" $output_style $config_old_path $config_new_path")
      ->run()
      ->getMessage();

    $results_array = explode("\n", $results);

    return $results_array;
  }

  /**
   * Synchronise active config to the install profile or specified path.
   *
   * Synchronise the differences from the configured 'config_new' and
   * 'config_old' directories into the install profile or a specific path.
   *
   * @param array $path
   *   If the sync is to update an entity instead of a profile, supple a path.
   */
  public function configSync($path = NULL) {
    $config_sync_already_run = FALSE;
    $output_path = $this->application_root . '/profiles/' . $this->getDrupalProfile() . '/config/install';
    $config_new_path = $this->application_root . '/' . $this->config_new_directory;

    // If a path is passed in, use it to override the destination.
    if (!empty($path) && is_dir($path)) {
      $output_path = $path;
    }

    $results_array = $this->configChanges();

    $tasks = $this->taskFilesystemStack();

    foreach ($results_array as $line) {
      // Handle/remove blank lines.
      $line = trim($line);
      if (empty($line)) {
        continue;
      }

      // Never sync the extension file, it breaks things.
      if (stristr($line, 'core.extension.yml')) {
        continue;
      }

      // Break up the line into fields and put the parts in their place.
      $parts = explode(' ', $line);
      $config_new_file = $parts[3];
      $output_file_path = $output_path .
        preg_replace("/^" . str_replace('/', '\/', $config_new_path) ."/", '', $config_new_file);

      // If the source doesn't exist, we're removing it from the
      // destination in the profile.
      if (!file_exists($config_new_file)) {
        if (file_exists($output_file_path)) {
          $tasks->remove($output_file_path);
        }
        else {
          $config_sync_already_run = TRUE;
        }
      }
      else {
        $tasks->copy($config_new_file, $output_file_path);
      }
    }

    if ($config_sync_already_run) {
      $this->say("Config sync already run?");
    }

    $tasks->run();
  }

  /**
   * Turns on twig debug mode, autoreload on and caching off.
   */
  public function devTwigDebugEnable() {
    $this->devConfigWriteable();
    $this->taskReplaceInFile($this->services_yml)
      ->from('debug: false')
      ->to('debug: true')
      ->run();
    $this->taskReplaceInFile($this->services_yml)
      ->from('auto_reload: null')
      ->to('auto_reload: true')
      ->run();
    $this->taskReplaceInFile($this->services_yml)
      ->from('cache: true')
      ->to('cache: false')
      ->run();
    $this->devConfigReadOnly();
    $this->say('Clearing Drupal cache...');
    $this->devCacheRebuild();
    $this->say('Done. Twig debugging has been enabled');
  }

  /**
   * Turn off twig debug mode, autoreload off and caching on.
   */
  public function devTwigDebugDisable() {
    $this->devConfigWriteable();
    $this->taskReplaceInFile($this->services_yml)
      ->from('debug: true')
      ->to('debug: false')
      ->run();
    $this->taskReplaceInFile($this->services_yml)
      ->from('auto_reload: true')
      ->to('auto_reload: null')
      ->run();
    $this->taskReplaceInFile($this->services_yml)
      ->from('c: false')
      ->to('cache: true')
      ->run();
    $this->devConfigReadOnly();
    $this->say('Clearing Drupal cache...');
    $this->devCacheRebuild();
    $this->say('Done. Twig debugging has been disabled');
  }

  /**
   * Disable asset aggregation.
   */
  public function devAggregateAssetsDisable() {
    $this->taskExecStack()
      ->exec($this->drush_cmd . ' cset system.performance js preprocess "false" -y')
      ->exec($this->drush_cmd . ' cset system.performance css preprocess "false" -y')
      ->run();
    $this->devCacheRebuild();
    $this->say('Asset Aggregation is now disabled.');
  }

  /**
   * Enable asset aggregation.
   */
  public function devAggregateAssetsEnable() {
    $this->taskExecStack()
      ->exec($this->drush_cmd . ' cset system.performance js preprocess "true" -y')
      ->exec($this->drush_cmd . ' cset system.performance css preprocess "true" -y')
      ->run();
    $this->devCacheRebuild();
    $this->say('Asset Aggregation is now enabled.');
  }

  /**
   * Make config files write-able.
   */
  public function devConfigWriteable() {
    $this->setPermissions("$this->application_root/sites/default/services.yml", '0664');
    $this->setPermissions("$this->application_root/sites/default/settings.php", '0664');
    $this->setPermissions("$this->application_root/sites/default/settings.local.php", '0664');
    $this->setPermissions("$this->application_root/sites/default", '0775');
  }

  /**
   * Make config files read only.
   */
  public function devConfigReadOnly() {
    $this->setPermissions("$this->application_root/sites/default/services.yml", '0444');
    $this->setPermissions("$this->application_root/sites/default/settings.php", '0444');
    $this->setPermissions("$this->application_root/sites/default/settings.local.php", '0444');
    $this->setPermissions("$this->application_root/sites/default", '0555');
  }

  /**
   * Check if file exists and set permissions.
   *
   * @param string $file
   *   File to modify.
   * @param string $permission
   *   Permissions. E.g. '0644'.
   */
  protected function setPermissions($file, $permission) {
    if (file_exists($file)) {
      $this->_exec("$this->sudo_cmd chmod $permission $file");
    }
  }

  /**
   * Return the name of the local user.
   *
   * @return string
   *   Returns the current user.
   */
  protected function getLocalUser() {
    $user = posix_getpwuid(posix_getuid());
    return $user['name'];
  }

  /**
   * Helper function to check whether a task has completed successfully.
   *
   * @param bool $successful
   *   Task ran successfully or not.
   * @param string $message
   *   Optional: A helpful message to print.
   */
  protected function checkFail($successful, $message = '') {
    if (!$successful) {
      $this->say('APP_ERROR: ' . $message);
      // Prevent any other tasks from executing.
      exit(1);
    }
  }

}
