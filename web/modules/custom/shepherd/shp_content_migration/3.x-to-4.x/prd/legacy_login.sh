#!/bin/bash

# oc login to the legacy cluster.
# dev
# oc get secret/shepherd-token-vpg6c -o jsonpath='{.data.token}' -n shepherd-dev | base64 -d  > LEGACY_TOKEN.txt
# prd
# oc get secret shepherd-token-kcvvv -o jsonpath='{.data.token}' -n shepherd-prd | base64 -d > LEGACY_TOKEN.txt
oc login --token=$(cat LEGACY_TOKEN.txt) --server=https://rhos-console.services.adelaide.edu.au:8443/
oc project shepherd-prd
