#!/bin/bash

# `set +e` is used to continue on errors throughout this script.
set -euo pipefail

# These need to be retrieved from the actual migration step.
SITE=$1
ENV=$2
NEW_SITE=$3
NEW_ENV=$4

./legacy_login.sh

oc rsh dc/${DC} drush cset readonlymode.settings enabled 1 -y

oc process -f legacy_backup.yml \
  -p LEGACY_SITE_NODE_ID=${SITE} \
  -p LEGACY_ENVIRONMENT_NODE_ID=${ENV} \
  -p NEW_SITE_NODE_ID=${NEW_SITE} \
  -p NEW_ENVIRONMENT_NODE_ID=${NEW_ENV} | oc apply -f -

echo "Use this command to monitor progress, ctrl+c to exit one 'Completed' is shown."
echo "oc get pods/backup-site-${SITE}-env-${ENV}"
