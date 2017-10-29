# Installation guide

This guide assumes a working knowledge of the `oc` command line tool. 

## Production

### Deploy Shepherd directly via the OpenShift UI.
Login as admin and Import the Shepherd OpenShift deployment template globally
```bash
oc login -u system:admin
oc create -f shepherd-openshift.yaml -n openshift
```
You can now click Add to project in the OpenShift ui to deploy Shepherd directly.

### Deploy Shepherd from the command line.
Example command line to process the template and set the install profile to a custom value. 
```bash
oc process -f shepherd-openshift.yml -p SHEPHERD_INSTALL_PROFILE=shepherd | oc create -f -
```

### Configuring Shepherd

Once Shepherd has been deployed there are a few manual configuration steps that are required. 

#### Configuring the Orchestration Provider

`/admin/config/shepherd/orchestration`

Ensure the orchestration provider is enabled and queued operations is selected.

`/admin/config/shepherd/orchestration/provider-settings`

- `endpoint` - set to the url of the OpenShift instance you deployed Shepherd to.
- `token` - Auth token for OpenShift. To generate:
```bash
# First get the service account
SERVICE_ACCOUNT = $(oc describe serviceaccount shepherd | grep Token | awk '{ print $2 }')
# Retrieve the token 
TOKEN = $(oc describe secret ${SERVICE_ACCOUNT} | grep "token:" | awk '{ print $2 }')
# Copy this into the token textarea.
echo $TOKEN
```
- `namespace` - This is the name of the project setup in OpenShift, all operations will be performed within this project.

#### Configuring the Database Provisioner


### Delete shepherd instances and storage
```bash
oc delete all -l app=shepherd
oc delete pvc shepherd-web-shared
```
