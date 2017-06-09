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

  protected function getDrupalProfile() {
    return "shepherd";
  }

  /**
   * Create default content for the Shepherd.
   */
  public function devContentGenerate() {
    $virtual_host = getenv("VIRTUAL_HOST");
    if (!empty($virtual_host)) {
      $this->_exec("$this->drush_cmd scr ShepherdContentGenerate.php --uri=$virtual_host");
    }
  }

}
