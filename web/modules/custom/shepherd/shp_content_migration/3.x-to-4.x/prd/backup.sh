#!/bin/bash

# `set +e` is used to continue on errors throughout this script.
set -euo pipefail

# These need to be retrieved from the actual migration step.
SITE=$1
ENV=$2
NEW_SITE=$3
NEW_ENV=$4

./legacy_login.sh

set +e
oc rsh dc/node-${ENV} drush cset readonlymode.settings enabled 1 -y
oc delete pods/backup-site-${SITE}-env-${ENV}
set -e

oc process -f legacy_backup.yml \
  -p LEGACY_SITE_NODE_ID=${SITE} \
  -p LEGACY_ENVIRONMENT_NODE_ID=${ENV} \
  -p NEW_SITE_NODE_ID=${NEW_SITE} \
  -p NEW_ENVIRONMENT_NODE_ID=${NEW_ENV} | oc apply -f -

echo "Use this command to see progress. Its done once 'Completed' is shown. Typically < 2 minutes"
echo "oc get pods/backup-site-${SITE}-env-${ENV}"
