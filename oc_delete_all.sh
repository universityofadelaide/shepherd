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

if oc get project shp-test > /dev/null 2>&1; then
  warning "Deleting shp-test project"
  oc delete project shp-test
fi

if oc get project shp-wordpress-test > /dev/null 2>&1; then
  warning "Deleting shp-wordpress-test project"
  oc delete project shp-wordpress-test
fi

if oc get project shepherd > /dev/null 2>&1; then
  warning "Deleting shepherd project"
  oc delete project shepherd
fi

echo ""
notice "Resource deletions requested, allow a few seconds for things to complete."
notice "Performing dsh stop, dsh start, robo build && robo dev:drupal-content-generate should then work."
echo ""
