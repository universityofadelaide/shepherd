#!/bin/bash

# Setup some functions to output warnings.
notice() { printf "\e[32;01m%s\e[39;49;00m\n" "$1"; }
warning() { printf "\e[33;01m%s\e[39;49;00m\n" "$1"; }
error() { printf "\e[31;01m%s\e[39;49;00m\n" "$1"; }

# Ensure script is NOT running inside a container - must be run from host.
if [ -f /.dockerenv ]; then
  error "Inception error - you can't run $0 within a docker container."
  exit 1
fi

# Are we logged in as local developer, if not, bail!
if [[ "$(oc whoami)" != "developer" ]]; then
  error "You are not logged in as developer, exiting for safety reasons."
  error "Please login to your local development OpenShift"
  exit 1
fi

if oc get project shp-uat-2 > /dev/null 2>&1; then
  warning "Deleting shp-uat-2 project"
  oc delete project shp-uat-2
fi

if oc get project shp-uat-3 > /dev/null 2>&1; then
  warning "Deleting shp-uat-3 project"
  oc delete project shp-uat-3
fi

if oc get project shepherd-uat > /dev/null 2>&1; then
  warning "Deleting shepherd-uat project"
  oc delete project shepherd-uat
fi

if oc get project shepherd-uat-datagrid > /dev/null 2>&1; then
  warning "Deleting shepherd-uat-datagrid project"
  oc delete project shepherd-uat-datagrid
fi

if oc get project shepherd-uat-operator > /dev/null 2>&1; then
  warning "Deleting shepherd-uat-operator project"
  oc delete project shepherd-uat-operator
fi

echo ""
notice "Resource deletions requested, allow a few seconds for things to complete."
notice "Performing dsh stop, dsh start, robo build && robo dev:drupal-content-generate should then work."
echo ""
