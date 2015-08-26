<?php
/**
 * @file
 * Contains \Robo\RoboFileBase.
 *
 * Implementation of class for Robo - http://robo.li/
 */

use \Symfony\Component\Yaml\Yaml;

/**
 * Class RoboFile.
 */
class RoboFileBase extends \Robo\Tasks {

  /**
   * Initialize config variables and apply overrides.
   */
  public function __construct() {
    $this->application_root     = "app";

    $this->composer_bin         = "/usr/local/bin/composer";
    $this->drush_bin            = "bin/drush";
    $this->drush_cmd            = "$this->drush_bin -r $this->application_root";
    $this->phpcs_bin            = "bin/phpcs";
    $this->webserver_restart    = "sudo service apache2 restart";

    $this->config_file          = "config.json";
    $this->settings_php         = "$this->application_root/sites/default/settings.php";
    $this->services_yml         = "$this->application_root/sites/default/services.yml";

    // Drupal specific config.
    $this->drupal_profile       = "ua";

    $this->database             = [
                                    'database'=> 'local',
                                    'username'=> 'drupal',
                                    'password'=> 'drupal',
                                    'host'=> 'localhost',
                                    'port'=> '3306',
                                    'driver'=> 'mysql',
                                    'prefix'=> ''
                                  ];

    $this->site                  = [
                                    'admin_email' => 'admin@localhost',
                                    'admin_password' => 'password',
                                    'admin_user' => 'admin',
                                    'site_token' => 'default#',
                                    'site_title' => 'Site Name',
                                    'top_menu_style' => 'mega_menu',
                                  ]; // Indenting hurts me.

    $this->prefer_dist          = FALSE;

    $this->drupal_settings_keys = ['databases', 'site_token'];

    // Import config.json and override local config parameters.
    $this->importConfig();
  }

  /**
   * Perform a full build on the project.
   */
  public function build($opts = ['prefer-dist' => FALSE]) {
    $start = new DateTime();
    $this->handleOptions($opts);
    $this->buildMake($opts);
    $this->buildInstall();
    $this->buildApplyConfig();
    $this->say('Total build duration: ' . date_diff(new DateTime(), $start)->format('%im %Ss'));
  }

  /**
   * Perform a build for automated deployments.
   *
   * Don't install anything, just build everything.
   */
  public function buildAuto($opts = ['prefer-dist' => TRUE]) {
    $this->handleOptions($opts);
    $this->buildMake($opts);
  }

  /**
   * Install the site on the target host.
   */
  public function buildTarget($opts = ['prefer-dist' => TRUE]) {
    $this->handleOptions($opts);
    $this->buildInstall();
    $this->buildApplyConfig();
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
   */
  public function buildMake($opts = ['prefer-dist' => FALSE]) {
    $this->handleOptions($opts);

    // Disable xdebug while running "composer install".
    $this->devXdebugDisable(['no-restart' => TRUE]);
    $this->_exec("$this->composer_bin install " .
      ($this->prefer_dist ? '--prefer-dist --no-progress' : '--prefer-source'));
    $this->devXdebugEnable(['no-restart' => TRUE]);
  }

  /**
   * Clean config and files, then install Drupal and module dependencies.
   */
  public function buildInstall() {
    if (is_dir("$this->application_root/sites/default")) {
      $this->devConfigWriteable();
      $this->_exec("sudo rm -fR $this->application_root/default/files");
    }
    $this->taskFilesystemStack()
      ->copy("$this->application_root/sites/default/default.settings.php",
        "$this->settings_php",
        TRUE)
      ->copy("$this->application_root/sites/default/default.services.yml",
        "$this->services_yml",
        TRUE)
      ->run();

    $this->say("Creating settings.local.php");
    $this->taskWriteToFile("$this->application_root/sites/default/settings.local.php")
      ->text($this->generateDrupalSettings())
      ->run();

    $this->_exec("$this->drush_cmd site-install $this->drupal_profile -y" .
      " --db-url=" . $this->getDatabaseUrl() .
      " --account-mail=" . $this->site['admin_email'] .
      " --account-name=" . $this->site['admin_user'] .
      " --account-pass=" . $this->site['admin_password'] .
      " --site-name='" . $this->site['site_title'] . "'");

    // Undo some of site-install's good work.
    $this->say("Creating settings.local.php");

    // Allow us to write to settings.php.
    $this->devConfigWriteable();

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

    $this->devCacheRebuild();
  }

  /**
   * Apply site configuration.
   */
  public function buildApplyConfig() {
    if (property_exists($this, 'drupal_config')) {
      $task_stack = $this->taskExecStack();
      foreach ($this->drupal_config as $drupal_config_name => $drupal_config_items) {
        foreach ($drupal_config_items as $key => $value) {
          $task_stack->exec($this->drush_cmd . ' cset ' . $drupal_config_name .
            ' ' . $key . ' "' . $value . '" -y');
        }
      }
      $task_stack->printed(FALSE)->stopOnFail(TRUE)->run();
      $this->devCacheRebuild();
      $this->say('Site configuration applied.');
    }
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
   */
  public function devXdebugEnable($opts = ['no-restart' => FALSE]) {
    $this->_exec("sudo php5enmod xdebug");
    if (!$opts['no-restart']) {
      $this->devRestartWebserver();
    }
  }

  /**
   * Remote debug disable.
   */
  public function devXdebugDisable($opts = ['no-restart' => FALSE]) {
    $this->_exec("sudo php5dismod xdebug");
    if (!$opts['no-restart']) {
      $this->devRestartWebserver();
    }
  }

  /**
   * Restart Webserver.
   */
  public function devRestartWebserver() {
    $this->_exec($this->webserver_restart);
  }

  /**
   * Turns on twig debug mode, autoreload on and caching off.
   */
  public function devTwigDebugEnable() {
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
    $this->say('Clearing Drupal cache...');
    $this->devCacheRebuild();
    $this->say('Done. Twig debugging has been enabled');
  }

  /**
   * Turn off twig debug mode, autoreload off and caching on.
   */
  public function devTwigDebugDisable() {
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
    $this->devXdebugDisable(['no-restart' => TRUE]);
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
   * Make config files write-able.
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
   * @param string File to modify.
   * @param int Permissions. E.g. 0644.
   */
  private function setPermissions($file_tasks, $file, $permission) {
    if (file_exists($file)) {
      $file_tasks->chmod($file, $permission);
    }
  }

  /**
   * Handle command line options.
   */
  private function handleOptions($opts) {
    if (isset($opts['prefer-dist'])) {
      $this->prefer_dist = (bool) $opts['prefer-dist'];
    }
  }

  /**
   * Parse and import config file if it exists.
   */
  private function importConfig() {
    if (file_exists($this->config_file)) {
      $config = json_decode(file_get_contents($this->config_file), TRUE);

      // Override local config items with those in the config file.
      foreach ($config as $config_key => $config_item) {
        $this->{$config_key} = $config_item;
      }
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
    $top_level_settings = [
      'base_url',
      'config',
      'config_directories',
      'databases',
      'settings',
    ];

    // Format Drupal specific database settings.
    $drupal_settings['databases']['default']['default'] = $this->database;
    $drupal_settings['databases']['default']['default']['namespace'] = 'Drupal\\Core\\Database\\Driver\\mysql';
    $drupal_settings['databases']['default']['default']['prefix'] = '';

    // Fetch Docker host IP address from the environment variable.
    if ($this->database['host'] == '{docker_host_ip}') {
      $drupal_settings['databases']['default']['default']['host'] = $this->getDockerHostIP();
    }

    // Reverse proxy configuration.
    if (isset($this->platform['reverse_proxy_addresses']) && count($this->platform['reverse_proxy_addresses'])) {
      $drupal_settings['settings']['reverse_proxy'] = TRUE;
      $drupal_settings['settings']['reverse_proxy_addresses'] = $this->platform['reverse_proxy_addresses'];
      if (isset($this->platform['reverse_proxy_header'])) {
        $drupal_settings['settings']['reverse_proxy_header'] = $this->platform['reverse_proxy_header'];
      }
    }

    // Add each local config parameter that appears in the keys list, making
    // sure to wrap non top-level items in the settings array.
    foreach ($this->drupal_settings_keys as $key) {
      if (property_exists($this, $key)) {
        if (in_array($key, $top_level_settings)) {
          $drupal_settings[$key] = $this->{$key};
        }
        else {
          $drupal_settings['settings'][$key] = $this->{$key};
        }
      }
    }

    // If there are any, merge in settings from config.json
    if (property_exists($this, 'drupal_settings')) {
      $drupal_settings = array_merge_recursive($drupal_settings, $this->drupal_settings);
    }

    return $this->exportDrupalSettings($drupal_settings, $top_level_settings);
  }

  /**
   * Export Drupal configuration from array format to generated PHP code.
   *
   * @param $drupal_settings
   *   The configuration to be exported.
   * @param array $top_level_settings
   *   Process these keys as stand-alone variables instead of $settings.
   *
   * @return string
   *   Settings as PHP code.
   */
  protected function exportDrupalSettings($drupal_settings, $top_level_settings = []) {
    // From the array, generate the PHP code.
    $text = "<?php\n";
    foreach ($drupal_settings as $key => $value) {
      // The 'settings' key is treated differently to ensure it doesn't clobber.
      if (in_array($key, $top_level_settings)) {
        foreach ($value as $sub_key => $sub_value) {
          $text .= '$' . $key . '[\'' . $sub_key . '\'] = ' . var_export($sub_value, TRUE) . ";\n";
        }
      }
      else {
        $text .= '$' . $key . ' = ' . var_export($value, TRUE) . ";\n";
      }
    }
    return $text;
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
    $host = $this->database['host'];
    if ($this->database['host'] == '{docker_host_ip}') {
      $host = $this->getDockerHostIP();
    }

    return $this->database['driver'] . '://' . $this->database['username'] . ':' .
      $this->database['password'] . '@' . $host . ':' .
      $this->database['port'] . '/' . $this->database['database'];
  }
}
