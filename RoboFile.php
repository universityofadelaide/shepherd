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
    parent::__construct();
    // Put project specific overrides here, below the parent constructor.
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

  public function devWordpressContentGenerate() {
    $virtual_host = getenv("VIRTUAL_HOST");
    if (!empty($virtual_host)) {
      $this->_exec("$this->drush_cmd scr WordpressContentGenerate.php --uri=$virtual_host");
    }
  }

}
