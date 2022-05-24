#!/bin/bash

# Setup some functions to output warnings.
notice() {
  printf "\e[32;01m%s\e[39;49;00m\n" "$1"
}

warning() {
  printf "\e[33;01m%s\e[39;49;00m\n" "$1"
}

error() {
  printf "\e[31;01m%s\e[39;49;00m\n" "$1"
}

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

warning "Deleting deployment configs."
oc get dc -o=go-template='{{range .items}}{{.metadata.name}}{{"\n"}}{{end}}' | grep node | xargs -n1 -t oc delete dc

warning "Deleting cronjobs."
oc get cronjob -o=go-template='{{range .items}}{{.metadata.name}}{{"\n"}}{{end}}' | grep node | xargs -n1 -t oc delete cronjob

warning "Deleting pods."
oc get pod -o=go-template='{{range .items}}{{.metadata.name}}{{"\n"}}{{end}}' | grep node | xargs -n1 -t oc delete pod

warning "Deleting services."
oc get svc -o=go-template='{{range .items}}{{.metadata.name}}{{"\n"}}{{end}}' | grep node | xargs -n1 -t oc delete svc

warning "Deleting routes."
oc get route -o=go-template='{{range .items}}{{.metadata.name}}{{"\n"}}{{end}}' | grep node | xargs -n1 -t oc delete route

warning "Deleting pvc's."
oc get pvc -o=go-template='{{range .items}}{{.metadata.name}}{{"\n"}}{{end}}' | grep node | xargs -n1 -t oc delete pvc

warning "Deleting build config."
oc get bc -o=go-template='{{range .items}}{{.metadata.name}}{{"\n"}}{{end}}' | grep example-master | xargs -n1 -t oc delete bc

warning "Deleting image stream."
oc get imagestream -o=go-template='{{range .items}}{{.metadata.name}}{{"\n"}}{{end}}' | grep example | xargs -n1 -t oc delete imagestream

warning "Deleting service accounts."
oc get sa -o=go-template='{{range .items}}{{.metadata.name}}{{"\n"}}{{end}}' | grep shepherd-prd-provisioner | xargs -n1 -t oc delete sa

echo ""
notice "Performing dsh stop, dsh start, robo build && robo dev:drupal-content-generate should now work."
echo ""
