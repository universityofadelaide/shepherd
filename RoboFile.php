<?php
/**
 * Implementation of class for Robo - http://robo.li/
 * 
 * To Install robo:
 * wget -O bin/robo http://robo.li/robo.phar
 * chmod +x bin/robo
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
  }

  /**
   * Perform a full build on the project
   */
  function build() {
    $this->prepareMake();
    $this->make();
    $this->prepareInstall();
    $this->siteInstall();
    //$this->generate();
  }

  /**
   * Prepare the application root for the make
   */
  function prepareMake() {
    if (is_dir($this->application_root)) {
      $this->_exec("sudo chmod -R 775 $this->application_root/sites");
      $this->_exec("sudo rm -fR $this->application_root");
    }
  }

  /**
   * Install drupal ready to run site-install on
   */
  function make() {
    $this->_exec("$this->drush_binary make --working-copy $this->drush_make_file $this->application_root");
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

    $this->_exec("git -C $this->application_root/modules/ua/ua_modules remote set-url origin git@github.com:universityofadelaide/ua_modules.git");
  }

  /**
   * Prepare directories and config files for site installation
   */
  function prepareInstall() {
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
  }

  /**
   * Run drush site install to build the site from the install profile
   */
  function siteInstall() {
    $this->_exec("$this->drush_cmd site-install \
    $this->drupal_profile -y --db-url=$this->mysql_query_string \
      --account-mail=$this->admin_email --account-name=$this->admin_account \
      --account-pass=$this->admin_password --site-name='$this->site_name'");
    $this->cacheClear();
  }

  /**
   * Run gulp to generate the css etc
   */
  function generate() {
    $this->_exec("$this->gulp_bin");
  }

  /**
   * Run gulp to watch and auto generate css etc
   */
  function generateWatch() {
    $this->_exec("$this->gulp_bin watch &");
  }

  /**
   * Add the styleguide softlink
   */
  function styleguideLink() {
    if (!is_link("/vagrant/". $this->application_root ."/styleguide")) {
      $this->taskFilesystemStack()
        ->symlink("/vagrant/styleguide", "/vagrant/". $this->application_root ."/styleguide")
        ->run();
    }
    else {
      $this->say("Styleguide link not created - already exists");
    }
  }

  /**
   * Perform cache clear in the app directory
   */
  function cacheClear() {
    $this->_exec("$this->drush_cmd cr");
  }

  /**
   * Install adminer
   */
  function adminer() {
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
  function gitflowInit() {
    $this->say("Initial project setup. Adds user details to gitconfig, adds project specific aliases to bashrc.");
    $github_name  = $this->ask("Enter your GitHub name (e.g. Bob Rocks):");
    $github_email = $this->ask("Enter your GitHub email (e.g. bob@rocks.adelaide.edu.au):");
    $git_config   = "[user]\n    email = $github_email}\n    name = $github_name\n";
    $this->taskWriteToFile("~/.gitconfig")
      ->append($git_config)
      ->run();
  }

  /**
   * Remote debug enable
   */
  function xdebugEnable() {
    $this->_exec("sudo php5enmod xdebug");
    $this->_exec("sudo service apache2 restart");
  }

  /**
   * Remote debug disable
   */
  function xdebugDisable() {
    $this->_exec("sudo php5dismod xdebug");
    $this->_exec("sudo service apache2 restart");
  }
}

