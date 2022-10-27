#!/bin/bash

# `set +e` is used to continue on errors throughout this script.
set -euo pipefail

# These need to be retrieved from the actual migration step.
SITE=$1
ENV=$2
NEW_SITE=$3
NEW_ENV=$4

./new_login.sh

oc project shepherd-prd-${NEW_SITE}

set +e
oc delete backup/node-${ENV}-legacy
set -e

oc process -f new_backup.yml \
  -p LEGACY_ENVIRONMENT_NODE_ID=${ENV} \
  -p NEW_SITE_NODE_ID=${NEW_SITE} \
  -p NEW_ENVIRONMENT_NODE_ID=${NEW_ENV} | oc apply -f -

# Sleep a couple a seconds to let the cluster deal.
sleep 2

set +e
oc delete restore/node-${NEW_ENV}-migrate
set -e

oc process -f new_restore.yml \
  -p LEGACY_ENVIRONMENT_NODE_ID=${ENV} \
  -p NEW_SITE_NODE_ID=${NEW_SITE} \
  -p NEW_ENVIRONMENT_NODE_ID=${NEW_ENV} | oc apply -f -

echo ""
echo "Use this command to monitor progress, ctrl+c to exit one 'Completed' is shown."
echo "oc get pod/restore-node-${NEW_ENV}-migrate -w"
echo ""
echo "Disable readonly mode once the restore is done, then clear cache."
echo "oc rsh -n shepherd-prd-${NEW_SITE} dc/node-${NEW_ENV} drush cset readonlymode.settings enabled 0 -y"
echo "oc rsh -n shepherd-prd-${NEW_SITE} dc/node-${NEW_ENV} drush cr"
echo ""
