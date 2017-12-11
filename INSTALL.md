# Installation guide

This guide assumes a working knowledge of the `oc` command line tool.

## Production

### Create a project 
Before deploying Shepherd to OpenShift, we must create a project. 

```
# login with user credientals that have permissions to create new projects
oc login 
oc new-project shepherd-openshift
```

### Create a secret 
The `shepherd-openshift.yml` configuration file will construct the necessary objects for running Shepherd. Before you can run the script a SSH-Auth secret called `build-key` needs to be created so Shepherd can be cloned from GitHub. This can be done via the UI `/console/project/{project-name}/create-secret` and clicking on the create secret button OR via `oc` command line tool :

```bash
oc create secret generic build-key --from-file=ssh-privatekey={key_file}
```

[Read more about creating secrets](https://docs.openshift.com/container-platform/latest/dev_guide/secrets.html)


### Deploy Shepherd directly via the OpenShift UI.
Login as admin and Import the Shepherd OpenShift deployment template globally
```bash
oc login -u system:admin
oc create -f shepherd-openshift.yml -n openshift
```
You can now click Add to project in the OpenShift ui to deploy Shepherd directly.

### Deploy Shepherd from the command line.

#### Create a Service Account for Shepherd
Before we configure Shepherd to use OpenShift, we need to create a [Service Account](https://docs.openshift.com/container-platform/latest/dev_guide/service_accounts.html)
that will allow us to to communicate to OpenShift.

```bash
# Ensure that you are logged into OpenShift and using the project you deployed Shepherd on.
oc create serviceaccount shepherd
oc policy add-role-to-user admin system:serviceaccount:{MY_PROJECT_NAME}:shepherd
```

### Configure Shepherd

Once Shepherd has been deployed there are a few manual configuration steps that are required.

#### Configure the Orchestration Provider

Shepherd communicates to OpenShift via the [OpenShift Client](https://github.com/universityofadelaide/openshift-client) which uses the REST Api endpoints provided by OpenShift.

`/admin/config/shepherd/orchestration`

Ensure the orchestration provider is enabled and queued operations is selected.

`/admin/config/shepherd/orchestration/provider-settings`

- `endpoint` - set to the url of the OpenShift instance you deployed Shepherd to.
- `namespace` - This is the name of the project setup in OpenShift, all operations will be performed within this project.
- `token` - Auth token for OpenShift. This is required to authenticate requests between the Shepherd OpenShift Client and the OpenShift API.
   This token is generated via a [Service Account](https://docs.openshift.com/container-platform/latest/dev_guide/service_accounts.html) created in OpenShift.
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

#### Configure the Database Provisioner

Drupal requires a database to run and Shepherd triggers the provisioning of a database. Depending on your deployment requirements you may
decide to run a database inside or outside of openshift. The database needs to be accessible by OpenShift and Shepherd.
[Deploying MariaDB in OpenShift](#Deploying MariaDB in OpenShift ). 

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
[Read more about secrets](https://docs.openshift.com/container-platform/latest/dev_guide/secrets.html).

### Configure environment types

When environments are created you declare a type of environment the entity belongs to. An environment type is a taxonomy that describes it's name,
base domain (The base domain is used to populate urls) and delete protection (protects entitys grouped with this environment type from deletion).
This grouping will logically define your environment(s) when you deploy on a site : e.g dev, uat, prd. You can define the environment types as per your
organisations workflow. In this instance we will create 3 environment types : `Development`, `UAT` and `Production`.

To create the environment types:
`/admin/structure/taxonomy/manage/shp_environment_types/overview` click the add term button.

### Configure cron jobs for Shepherd 

The next step is to configure cron jobs in OpenShift, once database and orchestration providers have been configured. These cron jobs will process the Shepherd job queue and run Drupal cron. The cron jobs are defined in the 
`shepherd-openshift-cronjob.yml` configuration. First you should get the following parameters so they can be passed to the template :

- DATABASE_PASSWORD
- SHEPHERD_WEB_IMAGESTREAM 

The image stream provides a source for the built images, so that you can launch pods to serve the Shepherd application.
To obtain the `SHEPHERD_WEB_IMAGESTREAM` first retrieve the internal docker registry ip address:
You require need system admin access.

```bash
# login as the system user 
oc login -u system:admin && oc project openshift
OC_DOCKER_REGISTRY_IP=$(oc get is | tail -n1 | awk '{print $2}' | awk -F '/' '{print $1}')
# logout as system user
oc logout 
# create the variable to use.
SHEPHERD_WEB_IMAGESTREAM="${OC_DOCKER_REGISTRY_IP}/{PROJECT_NAME}/shepherd-web-is:latest"
```

Process and create the cron jobs:

```bash
oc process -f shepherd-openshift-cronjob.yml -p DATABASE_PASWORD=my-db-password \
 -p SHEPHERD_WEB_IMAGESTREAM=${SHEPHERD_WEB_IMAGESTREAM} | oc create -f -
```

### Delete Shepherd instances and storage
```bash
oc delete all -l app=shepherd
oc delete pvc shepherd-web-shared
```

### Deploying MariaDB in OpenShift 

This is an example of deploying a MariaDB service in OpenShift for use with Shepherd. Alternatives to running a single MariaDB database instance in OpenShift include connecting to a MariaDB Galera cluster or some other MySQL compatible master/slave configuration. 
However, this is the quickest option to get up and running.  

From the UI do the following : 
- Select `Add to Project`
- Select `Browse Catalog`
- Select either `MariaDB (Persistent)` or `MariaDB (Ephemeral)`(Without persistance).
- Select create (in this example we just use the defaults).

We now have a MariaDB service with a running pod. we need to make this accessible externally outside of OpenShift
so Shepherd can communicate with it so that Shepherd can manage databases via a service of type `LoadBalancer`.

With the `oc` command tool do the following:

```
# Expose mariadb with a LoadBalancer service.
oc expose dc mariadb --type=LoadBalancer --name=mariadb-external
# Add an annotation to tie the services together
oc annotate svc mariadb-external "service.alpha.openshift.io/dependencies=[{\"name\": \"mariadb\", \"kind\": \"Service\"}]"
``` 
This will create an externally exposed service that will be provided with an external ip and port number. To view this:

```
oc get svc mariadb-external
# Your output will look something like this :
NAME             CLUSTER-IP      EXTERNAL-IP                   PORT(S)          AGE
mariadb-external   172.30.26.100   172.29.100.67              3306:30012/TCP   3h
# External address is : 172.29.100.67:30012
```
