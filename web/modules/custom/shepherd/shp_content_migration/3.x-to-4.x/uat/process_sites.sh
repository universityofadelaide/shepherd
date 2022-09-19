#!/bin/bash

# `set +e` is used to continue on errors throughout this script.
set -euo pipefail

./legacy_login.sh

SHEPHERD=ua-shepherd-dc

# First, retrieve current list of routes.
oc get route -o jsonpath='{range .items[*]}{@.spec.host}{@.spec.path}{"\t"}{@.metadata.name}{"\n"}{end}' > routes.txt

for i in $(cat daily_sites.txt)
do
  set +e
  ENVIRONMENT=$(egrep "$i\\s+" routes.txt | awk '{ print $2 }')
  if [ -z ${ENVIRONMENT} ]; then
    continue
  fi
  set -e
  DC=$(oc get svc ${ENVIRONMENT} -o jsonpath='{.spec.selector.deploymentconfig}')
  ENVIRONMENT="${ENVIRONMENT/node-/}"
  SITE=$(oc get dc/${DC} -o jsonpath='{.metadata.labels.site_id}')
  echo "Process for: $i"
  echo "./new_login.sh"
  echo "oc rsh dc/${SHEPHERD} drush mim shp_site --idlist=${SITE}:en -l https://shepherd-uat.apps.ocp-blue.adelaide.edu.au/"
  echo "oc rsh dc/${SHEPHERD} drush mim shp_environment --idlist=${ENVIRONMENT}:en -l https://shepherd-uat.apps.ocp-blue.adelaide.edu.au/"
  echo "NEW_SITE=\$(oc rsh dc/${SHEPHERD} drush sqlq \"SELECT destid1 FROM migrate_map_shp_site WHERE sourceid1 = ${SITE};\" | tr -d '\r')"
  echo "NEW_ENV=\$(oc rsh dc/${SHEPHERD} drush sqlq \"SELECT destid1 FROM migrate_map_shp_environment WHERE sourceid1 = ${ENVIRONMENT};\" | tr -d '\r')"
  echo "./backup.sh ${SITE} ${ENVIRONMENT} \${NEW_SITE} \${NEW_ENV}"
  echo "Wait for backup to finish."
  echo "./restore.sh ${SITE} ${ENVIRONMENT} \${NEW_SITE} \${NEW_ENV}"
  echo "======================================="
done
