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
   * Custom import db with some sefety built in.
   *
   * @param string $sql_file
   *
   * @throws \Robo\Exception\TaskException
   */
  public function devImportDb($sql_file) {
    $domain_name = getenv("OPENSHIFT_DOMAIN") ?: '192.168.99.100.nip.io';
    $openshift_url = getenv("OPENSHIFT_URL") ?: 'https://192.168.99.100:8443';
    $database_host = getenv("DB_HOST") ?: 'mysql-myproject.' . $domain_name;
    $token = trim(getenv("TOKEN"));

    $start = new DateTime();
    $this->_exec("$this->drush_cmd -y sql-drop");
    $this->_exec("$this->drush_cmd sqlq --file=$sql_file");
    $this->taskExecStack()
      ->exec("$this->drush_cmd -y cset shp_database_provisioner.settings host $database_host")
      ->exec("$this->drush_cmd -y cset shp_database_provisioner.settings user root")
      ->exec("$this->drush_cmd -y cget shp_database_provisioner.settings")
      ->exec("$this->drush_cmd -y cset shp_orchestration.settings connection.namespace myproject")
      ->exec("$this->drush_cmd -y cset shp_orchestration.settings connection.endpoint $openshift_url")
      ->exec("$this->drush_cmd -y cset shp_orchestration.settings connection.token $token")
      ->exec("$this->drush_cmd -y cget shp_orchestration.settings")
      ->exec("$this->drush_cmd -y pmu cas")
      ->run();
    $this->_exec("$this->drush_cmd cr");
    $this->_exec("$this->drush_cmd updb --entity-updates -y");
    $this->say('Duration: ' . date_diff(new DateTime(), $start)->format('%im %Ss'));
    $this->_exec("$this->drush_cmd upwd admin password");
    $this->say('Database imported, admin user password is : password');
  }

}
