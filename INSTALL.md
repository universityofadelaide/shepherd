# Installation guide

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

### Delete shepherd instances and storage
```bash
oc delete all -l app=shepherd
oc delete pvc shepherd-web-shared
```
