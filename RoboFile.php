<?php
/**
 * @file
 * Contains \Robo\RoboFile.
 *
 * Implementation of class for Robo - http://robo.li/
 */

use \Symfony\Component\Yaml\Yaml;

/**
 * Class RoboFile.
 */
class RoboFile extends \Robo\Tasks {

  /**
   * Initialize config variables and apply overrides.
   */
  public function __construct() {
    $this->application_root = "app";

    $this->bundle_bin = "/usr/local/bin/bundle";
    $this->composer_bin = "/usr/local/bin/composer";
    $this->drush_bin = "bin/drush";
    $this->drush_cmd = "$this->drush_bin -r $this->application_root";
    $this->gulp_bin = "node_modules/.bin/gulp";
    $this->npm_bin = "/usr/bin/npm";
    $this->phpcs_bin = "bin/phpcs";

    $this->drupal_profile = "ua_site_manager";
    $this->site_name = "Site manager";

    $this->mysql_query_string = "mysql://drupal:drupal@localhost/local";

    $this->admin_email = "user@example.com";
    $this->admin_account = "admin";
    $this->admin_password = "password";

    $this->working_copy_clone = TRUE;

    $this->drush_make_file = "ua_site_manager.make.yml";
    $this->settings_php = "$this->application_root/sites/default/settings.php";
    $this->services_yml = "$this->application_root/sites/default/services.yml";

    // Apply local environment overrides.
    if (file_exists('RoboFile.local.php')) {
      include_once 'RoboFile.local.php';
      if (isset($local_conf) && is_array($local_conf)) {
        foreach ($local_conf as $key => $value) {
          $this->{$key} = $value;
        }
      }
    }
  }

  /**
   * Perform a full build on the project.
   */
  public function build($opts = ['working-copy' => FALSE, 'mysql-querystring' => NULL]) {
    $this->handleOptions($opts);
    $this->buildInit();
    $this->buildPrepare();
    $this->buildMake();
    $this->buildInstall($opts);
  }

  /**
   * Perform a build for automated deployments.
   *
   * Don't install anything, just build everything.
   */
  public function buildAuto($opts = ['working-copy' => FALSE, 'mysql-querystring' => NULL]) {
    $this->handleOptions($opts);
    $this->buildInit();
    $this->buildPrepare();
    $this->buildMake();
  }

  /**
   * Install the site on the target host.
   */
  public function buildTarget($opts = ['working-copy' => FALSE, 'mysql-querystring' => NULL]) {
    $this->handleOptions($opts);
    $this->buildInstall();
  }

  /**
   * Initialize the project by installing php, node and ruby dependencies.
   */
  public function buildInit() {
    $this->taskParallelExec()
      ->process("$this->composer_bin install --prefer-dist")
      ->process("$this->bundle_bin install")
      ->process("$this->npm_bin install")
      ->run();
  }

  /**
   * Prepare the application root for the make.
   */
  public function buildPrepare() {
    if (is_dir($this->application_root)) {
      $this->_exec("sudo chmod -R 775 $this->application_root/sites");
      $this->_exec("sudo rm -fR $this->application_root");
    }
  }

  /**
   * Run drush make to build the application code from dependencies.
   */
  public function buildMake() {
    $clone_type = $this->working_copy_clone ? "--working-copy" : "--shallow-clone";
    $this->_exec("$this->drush_bin make $clone_type $this->drush_make_file $this->application_root");
    $this->taskFilesystemStack()
      ->remove("$this->application_root/modules/custom")
      ->remove("$this->application_root/themes/custom")
      ->remove("$this->application_root/profiles")
      ->run();
    $this->taskFilesystemStack()
      ->symlink("../../modules", "$this->application_root/modules/custom")
      ->symlink("../../themes", "$this->application_root/themes/custom")
      ->symlink("../profiles", "$this->application_root/profiles")
      ->run();
  }

  /**
   * Clean config and files, then install Drupal and module dependencies.
   */
  public function buildInstall($opts = ['working-copy' => FALSE, 'mysql-querystring' => NULL]) {
    $this->handleOptions($opts);
    if (is_dir("$this->application_root/sites/default")) {
      $this->_exec("sudo chmod -R 775 $this->application_root/sites/default");
      $this->_exec("sudo rm -fR $this->application_root/default/files");
    }
    $this->taskFilesystemStack()
      ->copy("$this->application_root/sites/default/default.settings.php",
        "$this->settings_php")
      ->copy("$this->application_root/sites/default/default.services.yml",
        "$this->services_yml")
      ->chmod("$this->settings_php", 0664)
      ->chmod("$this->services_yml", 0664)
      ->run();
    $this->_exec("$this->drush_cmd site-install " .
      "$this->drupal_profile -y --db-url=$this->mysql_query_string " .
      "--account-mail=$this->admin_email --account-name=$this->admin_account " .
      "--account-pass=$this->admin_password --site-name='$this->site_name'");
    $this->devCacheClear();

    $this->_exec("$this->drush_cmd composer-manager-init");
    $this->taskExec("$this->composer_bin drupal-update")
      ->dir("$this->application_root/core")
      ->run();
  }

  /**
   * Perform cache clear in the app directory.
   */
  public function devCacheClear() {
    $this->_exec("$this->drush_cmd cr");
  }

  /**
   * Ask a couple of questions and then setup the initial config.
   */
  public function devInit() {
    $this->say("Initial project setup. Adds user details to gitconfig.");
    $git_name = $this->ask("Enter your Git name (e.g. Bob Rocks):");
    $git_email = $this->ask("Enter your Git email (e.g. bob@rocks.adelaide.edu.au):");
    $this->_exec("git config --global user.name \"$git_name\"");
    $this->_exec("git config --global user.email \"$git_email\"");
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
   * Remote debug enable.
   */
  public function devXdebugEnable() {
    $this->_exec("sudo php5enmod xdebug");
    $this->_exec("sudo service apache2 restart");
  }

  /**
   * Remote debug disable.
   */
  public function devXdebugDisable() {
    $this->_exec("sudo php5dismod xdebug");
    $this->_exec("sudo service apache2 restart");
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
    $this->say('Running drush cr as well ..');
    $this->devCacheClear();
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
    $this->say('Running drush cr as well ..');
    $this->devCacheClear();
    $this->say('Done. Twig debugging has been disabled');
  }

  /**
   * Run PHP Code Sniffer.
   */
  public function devPhpcs() {
    $this->_exec("$this->phpcs_bin --config-set installed_paths vendor/drupal/coder/coder_sniffer");
    $this->_exec("$this->phpcs_bin --standard=Drupal --extensions=php,inc,module,theme,install,profile modules themes profiles");
  }

  /**
   * Handle command line options.
   */
  private function handleOptions($opts) {
    if (isset($opts['shallow-clone'])) {
      $this->working_copy_clone = FALSE;
    }
    if (isset($opts['mysql-querystring'])) {
      $this->mysql_query_string = $opts['mysql-querystring'];
    }
  }

}
