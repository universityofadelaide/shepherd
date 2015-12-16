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

  /**
   * Initialize config variables and apply overrides.
   */
  public function __construct() {
    $this->application_root     = "app";

    $this->composer_bin         = "/usr/local/bin/composer";
    $this->drush_bin            = "bin/drush";
    $this->drush_cmd            = "$this->drush_bin -r $this->application_root";
    $this->phpcs_bin            = "phpcs";
    $this->webserver_restart    = "sudo service apache2 restart";

    $this->config_file          = "config.json";
    $this->config_default_file  = "config.default.json";
    $this->settings_php         = "$this->application_root/sites/default/settings.php";
    $this->services_yml         = "$this->application_root/sites/default/services.yml";

    $this->config_old_directory = 'config_old';
    $this->config_new_directory = 'config_new';

    $this->config = [];
    $this->importConfig();

    $this->drupal_profile       = '';
    $this->setDrupalProfile();
  }

  /**
   * Force projects to declare which install profile to use.
   *
   * I.e. $this->drupal_profile = 'some_profile'.
   */
  protected abstract function setDrupalProfile();

  /**
   * Parse and import config.
   */
  private function importConfig() {
    if (file_exists($this->config_file)) {
      $this->config = json_decode(file_get_contents($this->config_file), TRUE);
    }
    elseif (file_exists($this->config_default_file)) {
      $this->config = json_decode(file_get_contents($this->config_default_file), TRUE);
    }
    else {
      $this->say('Unable to find config file');
      exit(1);
    }

    if (!is_array($this->config)) {
      $this->say('Unable to decode config file');
      exit(1);
    }

  }

  /**
   * Perform a full build on the project.
   *
   */
  public function build() {
    $start = new DateTime();
    $this->buildMake();
    $this->initLocalSettings();
    $this->buildInstall();
    $this->writeLocalSettings();
    $this->includeLocalSettings();
    $this->setAdminPassword();
    $this->buildApplyConfig();
    $this->say('Total build duration: ' . date_diff(new DateTime(), $start)->format('%im %Ss'));
  }

  /**
   * Perform a build for automated deployments.
   *
   * @deprecated Will be removed once all projects move to current RoboFileBase.
   *
   * @see distributionBuild()
   */
  public function buildAuto() {
    $this->distributionBuild();
  }

  /**
   * Perform a build for automated deployments.
   *
   * Don't install anything, just build the code base.
   *
   */
  public function distributionBuild() {
    $this->buildKeys();
    $this->buildMake();
  }

  /**
   * Populates authorized keys with keys inside config file.
   */
  public function buildKeys() {
    $keys = $this->config['site']['keys'];
    if ($keys) {
      $add_keys_task = $this->taskWriteToFile($_SERVER['HOME'] . '/.ssh/authorized_keys');
      foreach ($keys as $key) {
        $add_keys_task->line($key);
      }
      $add_keys_task->run();
    }
  }

  /**
   * Install a brand new site for a given environment.
   *
   * @deprecated Will be removed once all projects move to current RoboFileBase.
   *
   * @see environmentBuild()
   */
  public function buildTarget() {
    $this->environmentBuild();
  }

  /**
   * Install a brand new site for a given environment.
   */
  public function environmentBuild() {
    $this->buildInstall();
    $this->initLocalSettings();
    $this->writeLocalSettings();
    $this->includeLocalSettings();
    $this->setAdminPassword();
    $this->buildApplyConfig();
  }

  /**
   * Rebuild the environment image.
   *
   * I.e. Deploy a new release.
   */
  public function environmentRebuild() {
    $this->initLocalSettings();
    $this->writeLocalSettings();
    $this->includeLocalSettings();
  }

  /**
   * Clean the application root in preparation for a new build.
   */
  public function buildClean() {
    $this->_exec("sudo chmod -R 775 $this->application_root/sites/default");
    $this->_exec("sudo rm -fR $this->application_root/core");
    $this->_exec("sudo rm -fR $this->application_root/modules/contrib");
    $this->_exec("sudo rm -fR $this->application_root/profiles/contrib");
    $this->_exec("sudo rm -fR $this->application_root/themes/contrib");
    $this->_exec("sudo rm -fR $this->application_root/sites/all");
    $this->_exec("sudo rm -fR bin");
    $this->_exec("sudo rm -fR vendor");
  }

  /**
   * Run composer install to fetch the application code from dependencies.
   *
   */
  public function buildMake() {

    // Disable xdebug while running "composer install".
    $this->devXdebugDisable(['no-restart' => TRUE]);
    $this->_exec("$this->composer_bin install");
    $this->devXdebugEnable(['no-restart' => TRUE]);
  }

  /**
   * Copy the default settings.
   */
  public function initLocalSettings() {
    $this->devConfigWriteable();

    $this->taskFilesystemStack()
      ->copy("$this->application_root/sites/default/default.settings.php",
        "$this->settings_php",
        TRUE)
      ->copy("$this->application_root/sites/default/default.services.yml",
        "$this->services_yml",
        TRUE)
      ->run();

    $this->devConfigReadOnly();
  }

  /**
   * Create a file for all local configuration.
   */
  public function writeLocalSettings() {
    $this->devConfigWriteable();

    $this->say("Creating settings.local.php");
    $this->taskWriteToFile("$this->application_root/sites/default/settings.local.php")
      ->text($this->generateDrupalSettings())
      ->run();

    $this->devConfigReadOnly();
  }

  /**
   * Clean up settings.php and include the local settings file.
   */
  public function includeLocalSettings() {
    $this->devConfigWriteable();

    $this->say("Creating default settings.php file");
    $this->taskReplaceInFile("$this->application_root/sites/default/settings.php")
      ->regex('/\$databases = array.*?\(.*?\);/s')
      ->to('$databases = [];')
      ->run();

    $this->taskWriteToFile("$this->application_root/sites/default/settings.php")
      ->append()
      ->text("## START LOCAL CONFIG ##\n" .
        "if (file_exists(__DIR__ . '/settings.local.php')) {\n" .
        "  include __DIR__ . '/settings.local.php';\n" .
        "}\n" .
        "## END LOCAL CONFIG ##\n\n")
      ->run();

    // Re-set settings.php permissions.
    $this->devConfigReadOnly();
  }

  /**
   * Set the administrative user password.
   */
  public function setAdminPassword() {
    $this->_exec("$this->drush_cmd user-password admin --password=" . $this->config['site']['admin_password']);
  }

  /**
   * Clean config and files, then install Drupal and module dependencies.
   */
  public function buildInstall() {
    $this->devConfigWriteable();

    $this->_exec("$this->drush_cmd site-install $this->drupal_profile -y" .
      " --db-url=" . $this->getDatabaseUrl() .
      " --account-mail=" . $this->config['site']['admin_email'] .
      " --account-name=" . $this->config['site']['admin_user'] .
      " --account-pass=" . $this->config['site']['admin_password'] .
      " --site-name='" . $this->config['site']['site_title'] . "'");

    // Re-set settings.php permissions.
    $this->devConfigReadOnly();

    $this->devCacheRebuild();
  }

  /**
   * Apply site configuration.
   */
  public function buildApplyConfig() {
    $custom_config = [
      'system.site' => [
        'name' => $this->config['site']['site_title'],
      ],
      'ua_footer.authoriser' => [
        'name' => $this->config['site']['authoriser_name'],
        'email' => $this->config['site']['authoriser_email'],
      ],
      'ua_footer.maintainer' => [
        'name' => $this->config['site']['maintainer_name'],
        'email' => $this->config['site']['maintainer_email'],
      ],
      'system.ua_menu' => [
        'top_menu_style' => $this->config['site']['top_menu_style'],
      ],
    ];

    if ($this->config['custom']) {
      $custom_config = array_merge($custom_config, $this->config['custom']);
    }

    $task_stack = $this->taskExecStack();
    foreach ($custom_config as $drupal_config_name => $drupal_config_items) {
      foreach ($drupal_config_items as $key => $value) {
        $task_stack->exec($this->drush_cmd . ' cset ' . $drupal_config_name . ' ' . $key . ' "' . $value . '" -y');
      }
    }
    $task_stack->run();

    $this->devCacheRebuild();
    $this->say('Site configuration applied.');
  }

  /**
   * Perform cache clear in the app directory.
   */
  public function devCacheRebuild() {
    $this->_exec("$this->drush_cmd cr");
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
   * Rebuild the Drupal core scaffold files.
   *
   * This only affects certain files in the root of the app folder.
   * @link https://github.com/drupal-composer/drupal-project/blob/8.x/scripts/drupal/update-scaffold
   */
  function devRebuildScaffold() {
    $this->_exec("$this->drush_cmd dl drupal-8 --destination=/tmp --drupal-project-rename=drupal-8 --quiet -y");
    $this->_exec("rsync -avz --delete /tmp/drupal-8/ $this->application_root \\
      --exclude=.gitkeep \\
      --exclude=autoload.php \\
      --exclude=composer.json \\
      --exclude=core \\
      --exclude=drush \\
      --exclude=example.gitignore \\
      --exclude=LICENSE.txt \\
      --exclude=README.txt \\
      --exclude=vendor");
    $this->_exec("sudo rm -rf /tmp/drupal-8");
  }

  /**
   * Remote debug enable.
   *
   * @param array $opts
   *   Handle setting global variables with command line options.
   */
  public function devXdebugEnable($opts = ['no-restart' => FALSE]) {
    $this->_exec("sudo php5enmod xdebug");
    if (!$opts['no-restart']) {
      $this->devRestartWebserver();
    }
  }

  /**
   * Remote debug disable.
   *
   * @param array $opts
   *   Handle setting global variables with command line options.
   */
  public function devXdebugDisable($opts = ['no-restart' => FALSE]) {
    $this->_exec("sudo php5dismod xdebug");
    if (!$opts['no-restart']) {
      $this->devRestartWebserver();
    }
  }

  /**
   * Export the current configuration to an 'old' directory.
   */
  public function configExportOld() {
    $this->_exec("$this->drush_cmd -y cex --destination=" . $this->config_old_directory);
  }

  /**
   * Export the current configuration to a 'new' directory.
   */
  public function configExportNew() {
    $this->_exec("$this->drush_cmd -y cex --destination=" . $this->config_new_directory);
  }

  /**
   * Synchronise the differences from the configured 'config_new' and 'config_old' directories
   * into the install profile or a specific path.
   *
   * @param array $path
   *   if the sync is to update an entity instead of a profile, the path can be passed in.
   */
  public function configSync($path) {
    $config_sync_already_run = FALSE;
    $output_path = $this->application_root . '/profiles/' . $this->drupal_profile . '/install';
    $config_new_path = $this->application_root . '/' . $this->config_new_directory;

    // If a path is passed in, use it to override the destination
    if (!empty($path) && is_dir($path)) {
      $output_path = $path;
    }

    $results_array = $this->configChanges();

    $tasks = $this->taskFilesystemStack();

    foreach ($results_array as $line) {
      // Handle/remove blank lines.
      $line = trim($line);
      if (empty($line)) { continue; }

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
   * Display files changed between 'config_old' and 'config_new' directories.
   *
   * @param array $opts
   *   specify whether to show the diff output or just list them.
   * @return array
   *   command output results.
   *
   */
  public function configChanges($opts = ['show|s' => FALSE]) {
    $output_style = '-qbr';
    $config_old_path = $this->application_root . '/' . $this->config_old_directory;

    if (isset($opts['show']) && $opts['show']) {
      $output_style = '-ubr';
    }

    $results = $this->taskExec("diff -N -I 'uuid:.*' -I \"   - 'file:.*\" $output_style $config_old_path $config_old_path")
      ->run()
      ->getMessage();

    $results_array = explode("\n", $results);

    return $results_array;
  }

  /**
   * Restart Web server.
   */
  public function devRestartWebserver() {
    $this->_exec($this->webserver_restart);
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
      ->exec($this->drush_cmd . ' cset system.performance js gzip "false" -y')
      ->exec($this->drush_cmd . ' cset system.performance css gzip "false" -y')
      ->run();
    $this->devCacheRebuild();
    $this->say('Asset Aggregation is now disabled.');
  }

  /**
   * Enable asset aggregation.
   */
  public function devAggregateAssetsEnable() {
    $this->taskExecStack()
      ->exec($this->drush_cmd . ' cset system.performance js gzip "true" -y')
      ->exec($this->drush_cmd . ' cset system.performance css gzip "true" -y')
      ->run();
    $this->devCacheRebuild();
    $this->say('Asset Aggregation is now enabled.');
  }

  /**
   * Run PHP Code Sniffer.
   */
  public function devPhpcs() {
    $this->_exec("$this->phpcs_bin --config-set installed_paths vendor/drupal/coder/coder_sniffer");
    $this->_exec("$this->phpcs_bin --standard=Drupal --extensions=php,inc,module,theme,install,profile modules themes profiles");
  }

  /**
   * Composer update wrapper.
   */
  public function devComposerUpdate() {
    $this->devXdebugDisable(['no-restart' => TRUE]);
    $this->_exec("$this->composer_bin update");
    $this->devXdebugEnable(['no-restart' => TRUE]);
  }

  /**
   * Make config files write-able.
   */
  public function devConfigWriteable() {
    $file_tasks = $this->taskFilesystemStack();
    $this->setPermissions($file_tasks, "$this->application_root/sites/default/services.yml", 0644);
    $this->setPermissions($file_tasks, "$this->application_root/sites/default/settings.php", 0644);
    $this->setPermissions($file_tasks, "$this->application_root/sites/default/settings.local.php", 0644);
    $file_tasks->chmod("$this->application_root/sites/default", 0755);
    $file_tasks->run();
  }

  /**
   * Make config files read only.
   */
  public function devConfigReadOnly() {
    $file_tasks = $this->taskFilesystemStack();
    $this->setPermissions($file_tasks, "$this->application_root/sites/default/services.yml", 0444);
    $this->setPermissions($file_tasks, "$this->application_root/sites/default/settings.php", 0444);
    $this->setPermissions($file_tasks, "$this->application_root/sites/default/settings.local.php", 0444);
    $file_tasks->chmod("$this->application_root/sites/default", 0555);
    $file_tasks->run();
  }

  /**
   * Check if file exists and set permissions.
   *
   * @param FilesystemStack
   * @param string $file
   *   File to modify.
   * @param int $permission
   *   Permissions. E.g. 0644.
   */
  private function setPermissions($file_tasks, $file, $permission) {
    if (file_exists($file)) {
      $file_tasks->chmod($file, $permission);
    }
  }

  /**
   * Generates text in the format of Drupal settings.php from local config.
   *
   * @return string
   *   Settings.php text, intended to be output to file.
   */
  protected function generateDrupalSettings() {
    $drupal_settings = [];

    // Enable fast 404.
    $drupal_settings['config']['system.performance']['fast_404']['exclude_paths'] = '/\/(?:styles)|(?:system\/files)\//';
    $drupal_settings['config']['system.performance']['fast_404']['paths'] = '/\.(?:txt|png|gif|jpe?g|css|js|ico|swf|flv|cgi|bat|pl|dll|exe|asp)$/i';
    $drupal_settings['config']['system.performance']['fast_404']['html'] = '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL "@path" was not found on this server.</p></body></html>';

    // Set site_id in php file so that it is immutable.
    $drupal_settings['settings']['site_id'] = $this->config['site']['id'];

    // Set the hash_salt from config.
    $drupal_settings['settings']['hash_salt'] = $this->config['environment']['hash_salt'];

    // Format Drupal specific database settings.
    $drupal_settings['databases']['default']['default'] = $this->config['database'];
    $drupal_settings['databases']['default']['default']['namespace'] = 'Drupal\\Core\\Database\\Driver\\mysql';
    $drupal_settings['databases']['default']['default']['prefix'] = '';

    // Fetch Docker host IP address from the environment variable.
    if ($this->config['database']['host'] == '{docker_host_ip}') {
      $drupal_settings['databases']['default']['default']['host'] = $this->getDockerHostIP();
    }

    // Reverse proxy configuration.
    if ($this->config['platform']['reverse_proxy_addresses']) {
      $drupal_settings['settings']['reverse_proxy'] = TRUE;
      $drupal_settings['settings']['reverse_proxy_addresses'] = $this->config['platform']['reverse_proxy_addresses'];
      if ($this->config['platform']['reverse_proxy_header']) {
        $drupal_settings['settings']['reverse_proxy_header'] = $this->config['platform']['reverse_proxy_header'];
      }
    }

    $code = "<?php\n";
    $code .= $this->generatePhpCodeFromArray($drupal_settings);
    return $code;
  }

  /**
   * Generates php code from an array in a non clobbering recursive fashion.
   *
   * @param array $array
   *   the array to convert to php code.
   * @param string $code
   *   code being generated.
   * @param array $parents
   *   parent array keys.
   * @return string $code
   *   the generated php code.
   */
  protected function generatePhpCodeFromArray($array, $code = '', $parents = []) {
    foreach ($array as $key => $val) {
      $new_parents = array_merge($parents, [$key]);
      if (is_array($array[$key])) {
        $code = $this->generatePhpCodeFromArray($val, $code, $new_parents);
      }
      else {
        // @TODO: escape quotes and slashes in key and value.
        foreach($new_parents as $index => $parent) {
          if ($index == 0) {
            $code .= '$' . $parent;
          }
          else if (is_int($parent)) {
            $code .= "[" . $parent . "]";
          }
          else {
            $code .= "['" . $parent . "']";
          }
        }
        if (is_bool($val)) {
          $code .= ' = ' . ($val ? 'TRUE' : 'FALSE') . ";\n";
        }
        else {
          $code .= " = '" . $val . "';\n";
        }
      }
    }
    return $code;
  }

  /**
   * Get the Docker host IP address from the environment.
   *
   * @return string
   *   The IPv4 address of the Docker host.
   */
  private function getDockerHostIP() {
    return trim($this->taskExec('netstat -nr | grep \'^0\.0\.0\.0\' | awk \'{print $2}\'')->run()->getMessage());
  }

  /**
   * Generates a database url from full db config.
   *
   * @return string
   *   A database url of the format mysql://user:pass@host:port/db_name
   */
  private function getDatabaseUrl() {
    $host = $this->config['database']['host'];
    if ($host == '{docker_host_ip}') {
      $host = $this->getDockerHostIP();
    }

    return
      $this->config['database']['driver'] . '://' .
      $this->config['database']['username'] . ':' .
      $this->config['database']['password'] . '@' . $host . ':' .
      $this->config['database']['port'] . '/' .
      $this->config['database']['database'];
  }
}
