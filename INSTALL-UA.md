# University of Adelaide Installation

This guide assumes a working knowledge of the `oc` command line tool.

## Configure a new Shepherd deployment on OpenShift

### Login and project selection
Visit [Openshift Console Command line tools](https://rhos-console.services.adelaide.edu.au:8443/console/command-line)
to find useful info about logging in.

DEV: https://rhosd-console.services.adelaide.edu.au:8443 shepherd-dev

PRD: https://rhos-console.services.adelaide.edu.au:8443 shepherd-prd

```bash
# Requires uni a-number to login and access to the Shepherd project.
oc login https://rhosd-console.services.adelaide.edu.au:8443

# Create a new `shepherd` project if doesn't exist.
oc new-project shepherd-dev

# Switch to the `shepherd-dev` project.
oc project shepherd-dev
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
oc create secret generic shepherd-db-password --from-literal=DATABASE_PASSWORD=MYPASSWORD
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
oc process -f ua-shepherd-openshift.yml -p SHEPHERD_INSTALL_PROFILE=ua_shepherd -p DATABASE_HOST=mariadb-web-uat2.adelaide.edu.au | oc create -f -
```

PRD

```bash
oc process -f ua-shepherd-openshift.yml -p SHEPHERD_INSTALL_PROFILE=ua_shepherd -p DATABASE_HOST=mariadb-web-prd2.adelaide.edu.au | oc create -f -
```

Find the url to Shepherd E.g. http://shepherd-web-route-wcms-test.openshift.services.adelaide.edu.au and run the
installer. Admin user password is in https://thycotic.ad.adelaide.edu.au under Drupal Team:shepherd-admin-password.

### Configure the orchestration provider

- Visit /admin/config/shepherd/orchestration and configure `OpenShift with Redis`.
- Visit /admin/config/shepherd/orchestration/provider-settings and configure:
- Endpoint: https://rhos-console.services.adelaide.edu.au:8443
- Token: {auth token - see `Configuring the Orchestration Provider` in INSTALL.md}
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

-- Host: mariadb-web-prd2.adelaide.edu.au
-- Port: 3306
-- User: prd_web2
-- Secret: privileged-db-password (Thycotic: shepherd-db2-password)

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
oc process -f ua-shepherd-openshift-cronjob.yml -p SHEPHERD_WEB_IMAGESTREAM=${SHEPHERD_WEB_IMAGESTREAM} -p DATABASE_HOST=mariadb-web-prd2.adelaide.edu.au | oc create -f -
```

#### OpenShift < 3.6.1

You will need to run a job cleaner if cronjobs do not respect the failedJobHistoryLimit and successfulJobHistoryLimit settings.

https://github.com/pingers/openshift-scheduledjobs-cleanup is an image to do just this.
All credit to https://github.com/willemvd/openshift-scheduledjobs-cleanup - there's just a couple of minor changes.

Run the following to use it:

```bash
oc create serviceaccount scheduled-jobs-cleanup
oc policy add-role-to-user edit system:serviceaccount:$(oc project -q):scheduled-jobs-cleanup
oc process -f ua-shepherd-openshift-cronjob-cleaner.yml | oc create -f -
```

### Configure the environment types

- Visit /admin/structure/taxonomy/manage/shp_environment_types/overview and configure:

#### Dev environment type

-- Name: Dev
-- Base domain: dev.openshift.services.adelaide.edu.au
-- Protect from deletion: false
-- Update go live: false

#### Prd environment type

- Name: Prd
- Base domain: prd.openshift.services.adelaide.edu.au
- Protect from deletion: true
- Update go live: true

### Create WCMS project

- Title : WCMS
- Git repository: git@gitlab.adelaide.edu.au:web-team/ua-wcms-d8.git
- Build secret: build-key
- Default tag/branch: shepherd
- Builder image: uofa/s2i-shepherd-drupal

### Create Site

### Create Environment
