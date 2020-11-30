<?php

/**
 * @file
 * Contains \Robo\RoboFile.
 *
 * Implementation of class for Robo - http://robo.li/
 *
 * You may override methods provided by RoboFileBase.php in this file.
 * Configuration overrides should be made in the constructor.
 */

include_once 'RoboFileBase.php';

/**
 * Class RoboFile.
 */
class RoboFile extends RoboFileBase {

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    if ($root = getenv('SHEPHERD_ROOT')) {
      $this->configDir = $root . '/config-export';
      $this->configInstallDir = $root . '/config-install';
      $this->configDeleteList = $root . '/drush/config-delete.yml';
      $this->configIgnoreList = $root . '/drush/config-ignore.yml';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    parent::build();
    $this->say("To provide default content for shepherd, use robo dev:drupal-content-generate or robo dev:wordpress-content-generate");
  }

  /**
   * Create default content for the Shepherd.
   */
  public function devDrupalContentGenerate() {
    $virtual_host = getenv("VIRTUAL_HOST");
    if (!empty($virtual_host)) {
      $this->_exec("$this->drush_cmd scr DrupalContentGenerate.php --uri=$virtual_host");
    }
  }

  /**
   * Create default WP content for the Shepherd.
   */
  public function devWordpressContentGenerate() {
    $virtual_host = getenv("VIRTUAL_HOST");
    if (!empty($virtual_host)) {
      $this->_exec("$this->drush_cmd scr WordpressContentGenerate.php --uri=$virtual_host");
    }
  }

  /**
   * Create a dev login link.
   */
  public function devLogin() {
    $virtual_host = getenv("VIRTUAL_HOST");
    if (!empty($virtual_host)) {
      $this->_exec("$this->drush_cmd --uri=$virtual_host uli");
    }
  }

  /**
   * Set the owner and group of all files in the files dir to the web user.
   *
   * TESTING FOR TRAVIS.
   */
  public function buildSetFilesOwner() {
    $publicDir = getenv('PUBLIC_DIR') ?: $this->file_public_path;
    $privateDir = getenv('PRIVATE_DIR') ?: $this->file_private_path;
    $tmpDir = getenv('TMP_DIR') ?: $this->file_temp_path;
    foreach ([$publicDir, $privateDir, $tmpDir] as $path) {
      $this->say("Ensuring all directories exist.");
      $this->_exec("mkdir -p $path");
      $this->say("Setting directory permissions.");
      $this->setPermissions($path, '0775');
    }
  }

}
