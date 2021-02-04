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
    $this->_exec("$this->drush_cmd cr");
    $this->_exec("$this->drush_cmd updb --entity-updates -y");
    $this->taskExecStack()
      ->exec("$this->drush_cmd -y cset shp_database_provisioner.settings host $database_host")
      ->exec("$this->drush_cmd -y cset shp_database_provisioner.settings user root")
      ->exec("$this->drush_cmd -y cset shp_database_provisioner.settings populate_command \"wget [shepherd:public-filename] -O /tmp/dump.sql\r\ndrush sqlq --file=/tmp/dump.sql\r\ndrush updb -y\r\nrobo config:import-plus\r\ndrush cr\r\nrm /tmp/dump.sql\"")
      ->exec("$this->drush_cmd -y cget shp_database_provisioner.settings")
      ->exec("$this->drush_cmd -y cset shp_orchestration.settings connection.namespace myproject")
      ->exec("$this->drush_cmd -y cset shp_orchestration.settings connection.endpoint $openshift_url")
      ->exec("$this->drush_cmd -y cset shp_orchestration.settings connection.token $token")
      ->exec("$this->drush_cmd -y cset shp_orchestration.settings connection.verify_tls 0")
      ->exec("$this->drush_cmd -y cget shp_orchestration.settings")
      ->run();
    $this->configImportPlus();
    $this->_exec("$this->drush_cmd -y pmu cas");
    $this->say('Duration: ' . date_diff(new DateTime(), $start)->format('%im %Ss'));
    $this->_exec("$this->drush_cmd upwd admin password");
    $this->say('Database imported, admin user password is : password');
  }

}
