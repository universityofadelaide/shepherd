# University of Adelaide Installation

This guide assumes a working knowledge of the `oc` command line tool.

## Configure a new Shepherd deployment on OpenShift

### Login and project selection
Visit [Openshift Console Command line tools](https://console-openshift-console.apps.ocp-blue.adelaide.edu.au/command-line-tools)
to find useful info about logging in.

CLUSTER: https://console-openshift-console.apps.ocp-blue.adelaide.edu.au/

UAT: shepherd-uat
PRD: shepherd-prd

Login through the console UI then click on your name and 'Copy login command', then
Choose OKTA to get information on how to login to the cluster.
```bash
# Requires uni a-number to login and access to the Shepherd project.
oc login --token=sha256~randomlookingstuffhere --server=https://api.ocp-blue.adelaide.edu.au:6443

# Create a new project if doesn't exist.
oc new-project shepherd-uat

# Switch to the `shepherd-uat` project.
oc project shepherd-uat
```

### Secrets (passwords and keys)

Secrets can be created via the UI `console/project/wcms-test/create-secret`.
Some secret types cannot be created in the UI and only via the `oc` CLI.

Running Shepherd requires the following secrets to be defined:

#### privileged-db-password

Type: password

Thycotic:

DEV: shepherd-db-uat-password

PRD: shepherd-db2-password

Used for managing databases for sites and their environments.

#### shepherd-db-password

Type: password

Thycotic:

DEV: shepherd-site-db-uat-password

PRD: shepherd-site-db-password

Used by Shepherd itself to connect to its own Drupal database.

#### build-key

Type: ssh key

Thycotic: jenkins_deployment_key

Used to authenticate to GitLab and GitHub to clone repositories.

#### Create the secrets

```bash
# Replace MYPASSWORD with the password defined in Thycotic.
oc create secret generic privileged-db-password --from-literal=DATABASE_PASSWORD=MYPASSWORD
oc create secret generic build-key --from-file=ssh-privatekey=id_rsa

# Link the build key to the builder account.
oc secret link serviceaccount/builder secret/build-key
```

### OpenShift service account

Read [INSTALL.md](INSTALL.md#Create-a-Service-Account-for-Shepherd)

### Deploy Shepherd


Create the Shepherd instance from a pre-configured yaml manifest.

DEV

```bash
oc process -f shepherd-template.yaml -p SHEPHERD_INSTALL_PROFILE=shepherd -p DATABASE_HOST=<from thycotic> -p DATABASE_USER=<from thycotic> ... etc | oc apply -f -
```

PRD

```bash
oc process -f shepherd-template.yaml -p SHEPHERD_INSTALL_PROFILE=shepherd -p DATABASE_HOST=<from thycotic> -p DATABASE_USER=<from thycotic> ... etc | oc apply -f -
```

### Ensure access for the shepherd service account

```
oc project shepherd-prd
Now using project "shepherd-prd" on server "https://api.ocp-blue.adelaide.edu.au:6443".
oc adm policy add-role-to-user admin system:serviceaccount:shepherd-prd:shepherd-sa
oc project shepherd-prd-datagrid
Now using project "shepherd-prd-datagrid" on server "https://api.ocp-blue.adelaide.edu.au:6443".
oc adm policy add-role-to-user admin system:serviceaccount:shepherd-prd:shepherd-sa
```

### Setup the database password secret file
```
oc set volume dc/shepherd-prd --add --name=shepherd-prd-db --secret-name=shepherd-db-password \
--default-mode=0444 --mount-path=/etc/secret/DATABASE_PASSWORD --sub-path=DATABASE_PASSWORD
```

### Setup any extra settings
```
oc set volume dc/shepherd-prd --add --name=settings-app-php --configmap-name=settings-app-php \
--default-mode=0444 --mount-path=/code/web/sites/default/settings.app.php --sub-path=settings.app.php
```

### Shepherd url
Find the url to Shepherd by looking in the routes in the UI, or running an oc command:
```
oc get route
```

Do a local build.
```
dsh
robo build
drush sql-dump --result-file=/code/sql/shepherd.sql
exit
```

Find a pod, then Upload the sql, check the db credentials, then import the database.
```
oc get pods
oc cp ./sql/shepherd.sql shepherd-prd-9-w8npn:/shared/tmp/shepherd.sql
oc rsh shepherd-prd-9-w8npn
drush sql-connect
drush sqlq --file=/shared/tmp/shepherd.sql
```

### Configure the orchestration provider

- Visit /admin/config/shepherd/orchestration and configure `OpenShift with Redis`.
- Visit /admin/config/shepherd/orchestration/provider-settings and configure:
- Endpoint: https://rhosd-console.services.adelaide.edu.au:8443
- Token: {auth token - see `Configure the Orchestration Provider` in INSTALL.md}
- Verify TLS: TRUE
- Namespace: shepherd
- Save

### Configure the database provisioner

Visit /admin/config/shepherd/database-provisioner and configure:

DEV

-- Host: mariadb-web-uat2.adelaide.edu.au
-- Port: 3306
-- User: uat_web2
-- Secret: privileged-db-password (Thycotic: shepherd-db-uat-password)

PRD

-- Host: mariadb-drup-prd.adelaide.edu.au
-- Port: 3306
-- User: prd_web2
-- Secret: privileged-db-password (Thycotic: shepherd-db2-password)

### Configure LDAP for user search

Visit /admin/config/people/ldap and configure:

-- Hostname: ldap.adelaide.edu.au
-- Port 389
-- Base DN: dc=adelaide,dc=edu,dc=au
-- Users OU: ou=people

All other fields can be omitted. User and password are not needed, because Shepherd only needs to read from LDAP.

### Configure the service accounts

Visit /admin/config/shepherd/service-account and add new service accounts:

This will list them for easier entry.
List provisioners.
```
oc get sa | grep provisioner | awk '{ print $1 }'
```
List tokens with names to help matchup.
```
for j in $(for i in $(oc get sa | grep provisioner | awk '{ print $1 }')
do
  oc get sa/$i -o yaml; done | grep "\-token-" | awk '{ print $3 }')
  do printf "\n%s\n\n" $j; oc get secret/$j -o jsonpath='{.data.token}' | base64 -d
  echo ""
done
```

### Configure cron jobs for Shepherd

Cron jobs process the Shepherd job queue and run Drupal cron.
The cron jobs are defined in the `shepherd-openshift-cronjob.yml`.

First you should get the following parameters so they can be passed to the template:

- SHEPHERD_WEB_IMAGESTREAM

The image stream provides a source for the built images, so that you can launch pods to serve the Shepherd application.
To obtain the `SHEPHERD_WEB_IMAGESTREAM` first retrieve the internal docker registry ip address:
You require need system admin access.

```bash
# create the variables to use.
SHEPHERD_WEB_IMAGESTREAM="$(oc get is | tail -n1 | awk '{print $2}' | awk -F '/' '{print $1}')/$(oc project -q)/shepherd-web-is:latest"
```

Process and create the cron jobs:

DEV

```bash
oc process -f ua-shepherd-openshift-cronjob.yml -p SHEPHERD_WEB_IMAGESTREAM=${SHEPHERD_WEB_IMAGESTREAM} -p DATABASE_HOST=mariadb-web-uat2.adelaide.edu.au | oc create -f -
```

PRD

```bash
oc process -f ua-shepherd-openshift-cronjob.yml -p SHEPHERD_WEB_IMAGESTREAM=${SHEPHERD_WEB_IMAGESTREAM} -p DATABASE_HOST=mariadb-drup-prd.adelaide.edu.au | oc create -f -
```

### Configure the environment types

- Visit /admin/structure/taxonomy/manage/shp_environment_types/overview and configure:

#### Production OpenShift

##### Dev environment type

-- Name: Dev
-- Base domain: dev.openshift.services.adelaide.edu.au
-- Protect from deletion: false
-- Update go live: false

##### Prd environment type

- Name: Prd
- Base domain: prd.openshift.services.adelaide.edu.au
- Protect from deletion: true
- Update go live: true

#### Development OpenShift

##### Dev environment type

-- Name: Dev
-- Base domain: openshift.development.services.adelaide.edu.au
-- Protect from deletion: false
-- Update go live: false

### Create WCMS project

- Title : WCMS
- Git repository: git@gitlab.adelaide.edu.au:web-team/ua-wcms-d8.git
- Build secret: build-key
- Default tag/branch: shepherd-foundation
- Builder image: uofa/s2i-shepherd-drupal
- DEFAULT ENVIRONMENT VARIABLES: SHEPHERD_INSTALL_PROFILE: value: ua

### Create Site

### Create Environment
