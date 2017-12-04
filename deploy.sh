#!/bin/bash

OPENSHIFT_PROJECT_NAME="shepherd"

# Password for the mysql server.
SUPER_SECRET_PASSWORD=super-secret-password

# Path to ssh key with no passphrase.
: ${BUILD_KEY:=$HOME/.ssh/id_rsa}

# Are we logged in.
if [ ! $(oc whoami) ]; then
    # Prompt for login.
    oc login
fi

if ! oc get projects | grep -q ${OPENSHIFT_PROJECT_NAME}; then
  oc new-project ${OPENSHIFT_PROJECT_NAME}
fi

# Setup a new mysql server, expose it and group the services.
# This is for development environments only. Don't use this in production!
if ! oc get svc | grep -q mysql; then
  oc new-app mariadb MYSQL_ROOT_PASSWORD=${SUPER_SECRET_PASSWORD} -l db=shepherd
  oc expose dc mariadb --type=LoadBalancer --name=mysql-external
  oc annotate svc mysql-external "service.alpha.openshift.io/dependencies=[{\"name\": \"mariadb\", \"kind\": \"Service\"}]"
fi

# Setup the database password in an OpenShift secret.
if ! oc get secret | grep -q privileged-db-password; then
    oc create secret generic privileged-db-password --from-literal=DATABASE_PASSWORD=${SUPER_SECRET_PASSWORD}
fi

# Create the build key secret
# Add local ssh key as build-key secret if it exists and has no passphrase.
if ! oc get secret | grep -q build-key && [ -f ${BUILD_KEY} ] && ! grep -q ENCRYPTED ${BUILD_KEY}; then
    oc create secret generic build-key --from-file=ssh-privatekey=${BUILD_KEY}
fi

# Create a permanent token that shepherd can use to talk to OpenShift.
if ! oc get serviceaccount | grep -q shepherd; then
    oc create serviceaccount shepherd
    oc policy add-role-to-user admin system:serviceaccount:shepherd:shepherd
fi

# Retrieve the service account token
SERVICE_ACCOUNT=$(oc describe serviceaccount shepherd | grep Token | awk '{ print $2 }')

# This TOKEN is used for Auth in Shepherd to OpenShift
TOKEN=$(oc describe secret ${SERVICE_ACCOUNT} | grep "token:" | awk '{ print $2 }')

oc logout

# login as system user
oc login -u system:admin
oc project openshift
OC_DOCKER_REGISTRY_IP=$(oc get is | tail -n1 | awk '{print $2}' | awk -F '/' '{print $1}')
oc logout

# log back in as user
oc login
oc project ${OPENSHIFT_PROJECT_NAME}
oc process -f shepherd-openshift.yml -p SHEPHERD_INSTALL_PROFILE=shepherd | oc create -f -

OPENSHIFT_IP=$(oc status | grep 'server https' | sed 's/.*https:\/\/\([0-9a-z\.]*\).*/\1/')
OPENSHIFT_DOMAIN="${OPENSHIFT_IP}.nip.io"
DB_HOST="mysql-shepherd.${OPENSHIFT_DOMAIN}"
DB_PORT=$(oc get service mysql-external --no-headers | sed 's/.*:\([0-9]*\).*/\1/')

export TOKEN;
export DB_HOST
export DB_PORT

echo "Shepherd is now deploying on openshift."
echo "Please configure shepherd's orchestration provider and database provisioner. TOKEN, DB_HOST and DB_PORT have been exported."
echo "Once shepherd has been installed create the cronjob from shepherd-openshift-cronjob.yml"
