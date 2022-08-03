#!/bin/bash
#
# Usage ./rerun_migration.sh
#

# function called for each database.
function performimport() {
  # Reset any stuck migrations
  for migration in $(drush migrate:status --fields=id,status --format=string | grep -v Idle | grep -v ^$)
  do
    drush mrs $(echo $migration | awk  '{ print $1 }')
  done

  echo " Starting node types... "
  drush mim node_type_shp_site
  #drush mim node_type_shp_environment
}


# Ensure xdebug disabled
robo dev:xdebug-disable

# Ensure modules loaded
drush -y en shp_content_migration

# Re-import config
drush -y config-import --partial --source=/code/web/modules/custom/shepherd/shp_content_migration/config/install

drush ms

performimport

drush ms


# drush pmu -y shp_content_migration migrate migrate_drupal migrate_drupal_ui migrate_plus migrate_tools migrate_drupal_d8

exit
