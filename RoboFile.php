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
    $this->site_name = "Site Manager";
    $this->drupal_profile = "ua_site_manager";
  }

}

