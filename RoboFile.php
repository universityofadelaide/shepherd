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
   * {@inheritdoc}
   */
  public function build() {
    // Use the default build steps.
    parent::build();

    // Add default content.
    $this->say("Adding default content.");
    $this->devContentGenerate();
  }

  /**
   * Create default content for the Shepherd.
   */
  public function devContentGenerate() {
    $domain_name = getenv("DOMAIN");
    if (!empty($domain_name)) {
      $this->_exec("$this->drush_cmd scr ShepherdContentGenerate.php --uri=shepherd.$domain_name");
    }
  }

}
