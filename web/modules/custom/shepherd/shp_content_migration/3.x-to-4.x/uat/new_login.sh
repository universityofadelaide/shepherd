#!/bin/bash

# Create and store the token with:
# oc login to the new cluster.
# oc get secret/shepherd-sa-token-2n7dr -o jsonpath='{.data.token}' -n shepherd-uat | base64 -d > NEW_TOKEN.txt
oc login --token=$(cat NEW_TOKEN.txt) --server=https://api.ocp-blue.adelaide.edu.au:6443
oc project shepherd-uat
