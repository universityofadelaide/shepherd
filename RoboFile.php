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
   * @{inheritdoc}
   */
  public function __construct() {
    parent::__construct();
    // Put project specific overrides here, below the parent constructor.
  }

  protected function setDrupalProfile() {
    $this->drupal_profile = "ua_site_manager";
  }

  /**
   * @{inheritdoc}
   */
  public function build() {
    $start = new DateTime();

    // Default build process from parent::build().
    $this->buildMake();
    $this->initLocalSettings();
    $this->buildInstall();
    $this->writeLocalSettings();
    $this->includeLocalSettings();
    $this->setAdminPassword();
    $this->buildApplyConfig();

    // Add default content.
    $this->devContentGenerate();

    $this->say('Total build duration: ' . date_diff(new DateTime(), $start)->format('%im %Ss'));
  }

  /**
   * Create default content for the site manager.
   */
  public function devContentGenerate() {
    $this->_exec("$this->drush_cmd scr UASMContentGenerate.php");
  }

}
