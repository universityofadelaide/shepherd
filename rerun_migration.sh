#!/bin/bash
#
# Usage ./rerun_migration.sh
#

# function called for each database.
function performimport() {
  # Reset any stuck migrations
  for migration in $(drush migrate:status --group=shp_content_migration --fields=id,status --format=string | sed 's/\s/,/' | grep -v Idle)
  do
    drush mrs $(echo $migration | cut -d',' -f1)
  done

  echo " Importing taxonomy... "
  drush migrate:import taxonomy_terms

  echo " Starting node types... "
  drush migrate:import shp_project
  drush migrate:import shp_site
  robo dev:xdebug-enable
  drush migrate:import shp_environment
}

robo dev:xdebug-disable

drush scr MigrateGenerate.php --uri=${VIRTUAL_HOST}

# Ensure modules loaded
drush -y en shp_content_migration

# Re-import config
drush -y config-import --partial --source=/code/web/modules/custom/shepherd/shp_content_migration/config/install

drush migrate:status --group=shp_content_migration

performimport

drush migrate:status --group=shp_content_migration

# drush pmu -y shp_content_migration migrate migrate_drupal migrate_drupal_ui migrate_plus migrate_tools migrate_drupal_d8

exit
