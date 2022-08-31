<?php

/**
 * @file
 * Create Shepherd service accounts for use by `drush scr`.
 */

use Drupal\shp_service_accounts\Entity\ServiceAccount;

$etm = \Drupal::entityTypeManager();
$config = \Drupal::configFactory();
$domain_name = getenv("OPENSHIFT_DOMAIN") ?: '192.168.99.100.nip.io';
$openshift_url = getenv("OPENSHIFT_URL") ?: 'https://192.168.99.100:8443';

$database_host = getenv("DB_HOST") ?: 'mysql-external.' . $domain_name;
$database_port = getenv("DB_PORT") ?: '31632';

// Check that required variables are actually set.
$token = trim(getenv("TOKEN"));

if (empty($token)) {
  echo "To generate default configuration for development, the TOKEN variable is required to be set.\n";
  echo "Export your auth TOKEN from your host with.\n";
  echo "export TOKEN=some-token\n";
  echo "You can then safely re-run `robo dev:drupal-content-generate`\n";
  exit(1);
}

// Set deployment database config.
echo "Setting database deployment config.\n";
$db_provisioner_config = $config->getEditable('shp_database_provisioner.settings');
$db_provisioner_config->set('host', $database_host);
$db_provisioner_config->set('port', $database_port);
$db_provisioner_config->save();

// Set orchestration provider config.
echo "Setting orchestration provider config.\n";
$openshift_config = [
  'endpoint'           => $openshift_url,
  'token'              => $token,
  'namespace'          => 'shepherd-dev',
  'site_deploy_prefix' => 'shepherd-dev',
  'verify_tls'         => FALSE,
];
$orchestration_config = $config->getEditable('shp_orchestration.settings');
foreach ($openshift_config as $key => $value) {
  $orchestration_config->set('connection.' . $key, $value);
}
$orchestration_config->set('selected_provider', 'openshift_orchestration_provider');
$orchestration_config->save();

// Set datagrid cache config.
echo "Setting datagrid config.\n";
$cache_config = $config->getEditable('shp_cache_backend.settings');
$cache_config->set('namespace', 'shepherd-dev-datagrid');
$cache_config->save();

// Create config entities for the service accounts if they don't exist.
if (!$service_accounts = $etm->getStorage('service_account')->loadByProperties([])) {
  echo "Setting up Service accounts.\n";
  for ($i = 0; $i <= 4; $i++) {
    $label = sprintf("shepherd-dev-provisioner-00%02d", $i);
    $id = sprintf("shepherd_dev_provisioner_00%02d", $i);

    // This is pretty horrid, but there is no oc command in the dsh shell.
    $token = trim(file_get_contents("../.$label.token"));
    $account = ServiceAccount::create()
      ->set('label', $label)
      ->set('id', $id)
      ->set('status', TRUE)
      ->set('description', "Test provisioner $i")
      ->set('token', $token)
      ->save();
  }
}
else {
  echo "Service accounts already setup.\n";
}
