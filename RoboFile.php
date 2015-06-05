<?php
/**
 * Implementation of configuration/class for Robo - http://robo.li/
 *
 */

/**
 * Class RoboFile
 */
class RoboFile extends \Robo\Tasks {
  protected $application_root;
  protected $drupal_profile;
  protected $drush_binary;
  protected $drush_make_file;

  function __construct() {
    $this->application_root   = "app";

    $this->composer_bin       = "/usr/local/bin/composer";

    $this->drupal_profile     = "ua_site_manager";

    $this->drush_binary       = "bin/drush";
    $this->drush_make_file    = "ua_site_manager.make.yml";

    $this->drush_cmd          = "$this->drush_binary -r $this->application_root";

    $this->gulp_bin           = "node_modules/.bin/gulp";

    $this->mysql_query_string = "mysql://drupal:drupal@localhost/local";

    $this->admin_email        = "user@example.com";
    $this->admin_account      = "admin";
    $this->admin_password     = "password";

    $this->site_name          = "Site name";

    $this->private_key_path   = "/home/vagrant/.ssh/id_rsa";
  }

  /**
   * Perform a full build on the project
   */
  function build($opts = ['working-copy' => FALSE]) {
    $this->buildPrepare();
    $this->buildMake($opts);
    $this->buildInstall();
  }

  /**
   * Prepare the application root for the make
   */
  function buildPrepare() {
    if (is_dir($this->application_root)) {
      $this->_exec("sudo chmod -R 775 $this->application_root/sites");
      $this->_exec("sudo rm -fR $this->application_root");
    }
  }

  /**
   * Install drupal ready to run site-install on
   */
  function buildMake($opts = ['working-copy' => FALSE]) {
    $clone_type = "--shallow-clone";
    if ($opts['working-copy']) {
      $clone_type = "--working-copy";
    }

    $this->_exec("$this->drush_binary make $clone_type $this->drush_make_file $this->application_root");
    $this->taskFilesystemStack()
      ->remove("$this->application_root/modules/custom")
      ->remove("$this->application_root/themes/custom")
      ->remove("$this->application_root/profiles")
      ->run();
    $this->taskFilesystemStack()
      ->symlink("/vagrant/modules",   "/vagrant/$this->application_root/modules/custom")
      ->symlink("/vagrant/themes",    "/vagrant/$this->application_root/themes/custom")
      ->symlink("/vagrant/profiles",  "/vagrant/$this->application_root/profiles")
      ->run();
  }

  /**
   * Prepare directories and config files, then run drush site
   * install to build the site from the install profile
   */
  function buildInstall() {
    if (is_dir("$this->application_root/sites/default")) {
      $this->_exec("sudo chmod -R 775 $this->application_root/sites/default");
      $this->_exec("sudo rm -fR $this->application_root/default/files");
    }
    $this->taskFilesystemStack()
      ->copy("$this->application_root/sites/default/default.settings.php", "$this->application_root/sites/default/settings.php")
      ->copy("$this->application_root/sites/default/default.services.yml", "$this->application_root/sites/default/services.yml")
      ->chmod("$this->application_root/sites/default/settings.php", 777)
      ->chmod("$this->application_root/sites/default/services.yml", 777)
      ->run();
    $this->_exec("$this->drush_cmd site-install \
    $this->drupal_profile -y --db-url=$this->mysql_query_string \
      --account-mail=$this->admin_email --account-name=$this->admin_account \
      --account-pass=$this->admin_password --site-name='$this->site_name'");
    $this->_exec("$this->drush_cmd cr");
    $this->_exec("$this->drush_cmd composer-manager-init");
    $this->taskExec("$this->composer_bin drupal-update")
      ->dir("$this->application_root/core")
      ->run();
  }

  /**
   * Install adminer
   */
  function devInstallAdminer() {
    $this->taskFilesystemStack()
      ->remove("$this->application_root/adminer.php")
      ->run();

    $this->taskExec("wget -q -O adminer.php http://www.adminer.org/latest-mysql-en.php")
      ->dir($this->application_root)
      ->run();
  }

  /**
   * Ask a couple of questions and then setup the initial config
   */
  function devInit() {
    $this->say("Initial project setup. Adds user details to gitconfig, generate keys.");
    $git_name  = $this->ask("Enter your Git name (e.g. Bob Rocks):");
    $git_email = $this->ask("Enter your Git email (e.g. bob@rocks.adelaide.edu.au):");
    $this->_exec("git config --global user.name \"$git_name\"");
    $this->_exec("git config --global user.email \"$git_email\"");
    if ($this->confirm('Would you like to generate a new key pair?')) {
      $this->_exec("ssh-keygen -b 2048 -t rsa -q -N '' -f '$this->private_key_path'");
      $this->say("Add this new public key to your Git account.");
      echo file_get_contents($this->private_key_path . '.pub');
    }
  }

  /**
   * Remote debug enable
   */
  function devXdebugEnable() {
    $this->_exec("sudo php5enmod xdebug");
    $this->_exec("sudo service apache2 restart");
  }

  /**
   * Remote debug disable
   */
  function devXdebugDisable() {
    $this->_exec("sudo php5dismod xdebug");
    $this->_exec("sudo service apache2 restart");
  }
}

