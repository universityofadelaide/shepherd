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

PROJECTS=$(for i in $(oc get project -o jsonpath={.items[*].metadata.name}); do echo "$i"; done | grep -E "shepherd-dev-[0-9]+")
for j in ${PROJECTS}
do
  oc delete project $j
done

if oc get project shepherd-dev-datagrid > /dev/null 2>&1; then
  warning "Deleting shepherd-dev-datagrid project"
  oc delete project shepherd-dev-datagrid
fi

if oc get project shepherd-dev-operator > /dev/null 2>&1; then
  warning "Deleting shepherd-dev-operator project"
  oc delete project shepherd-dev-operator
fi

if oc get project shepherd-dev > /dev/null 2>&1; then
  warning "Deleting shepherd-dev project"
  oc delete project shepherd-dev
fi

echo ""
notice "Resource deletions requested, allow a few seconds for things to complete."
notice "Performing dsh stop, dsh, robo build && robo dev:drupal-content-generate should then work."
echo ""