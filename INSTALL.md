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

#### Create a Service Account for Shepherd
Before we configure Shepherd to use OpenShift, we need to create a [Service Account](https://docs.openshift.com/container-platform/3.5/dev_guide/service_accounts.html)
that will allow us to to communicate to OpenShift.

```bash
# Ensure that you are logged into OpenShift and using the project you deployed Shepherd on.
oc create serviceaccount shepherd
oc policy add-role-to-user admin system:serviceaccount:{MY_PROJECT_NAME}:shepherd
```

### Configuring Shepherd

Once Shepherd has been deployed there are a few manual configuration steps that are required.

#### Configuring the Orchestration Provider

`/admin/config/shepherd/orchestration`

Ensure the orchestration provider is enabled and queued operations is selected.

`/admin/config/shepherd/orchestration/provider-settings`

- `endpoint` - set to the url of the OpenShift instance you deployed Shepherd to.
- `namespace` - This is the name of the project setup in OpenShift, all operations will be performed within this project.
- `token` - Auth token for OpenShift. This is required to authenticate requests between the Shepherd OpenShift Client and the OpenShift API.
   This token is generated via a [Service Account](https://docs.openshift.com/container-platform/3.5/dev_guide/service_accounts.html) created in OpenShift.
   The below example assumes that a service account called `shepherd` has already been created, an API token will created automatically.
   To access our token:
```bash
# First get the service account
SERVICE_ACCOUNT=$(oc describe serviceaccount shepherd | grep Token | awk '{ print $2 }')
# Retrieve the token
TOKEN=$(oc describe secret ${SERVICE_ACCOUNT} | grep "token:" | awk '{ print $2 }')
# Copy this into the token textarea.
echo $TOKEN
```

#### Configuring the Database Provisioner

Ensure that the provisioner is enabled.

`/admin/config/shepherd/database-provisioner`

The following fields are required :
- `Host`
- `Port`
- `User` - Requires a privileged user that has permissions to `CREATE DATABASE` and `GRANT`
- `Secret` - This is the *name* of the secret which contains the privileged user password is stored. This should be setup as a secret in OpenShift.
Secrets can be created using the `oc` tool:
```bash
# Secrets are key, value pairs.
oc create secret generic privileged-db-password --from-literal=DATABASE_PASSWORD=SUPERSECRETPWD
# The name of your password is privileged-db-password and the key is DATABASE_PASSWORD.
```
[Read more about secrets](https://docs.openshift.com/container-platform/3.5/dev_guide/secrets.html).

### Configuring environment types

When environments are created you declare a type of environment the entity belongs to. An environment type is a taxonomy that describes it's name,
base domain (The base domain is used to populate urls) and delete protection (protects entitys grouped with this environment type from deletion).
This grouping will logically define your environment(s) when you deploy on a site : e.g dev, uat, prd. You can define the environment types as per your
organisations workflow. In this instance we will create 3 environment types : `Development`, `UAT` and `Production`.

To create the environment types:
`/admin/structure/taxonomy/manage/shp_environment_types/overview` click the add term button.

### Delete shepherd instances and storage
```bash
oc delete all -l app=shepherd
oc delete pvc shepherd-web-shared
```
