# Development setup

* [What is provided](#provided)
* [Prerequisites](#prerequisites)
* [Quickstart Guide](#quickstart)
* [Repository guide](#repository-details)
* [Shepherd development](#working-with-shepherd)
* [Troubleshooting](#troubleshooting)
* [Using Minishift](#working-with-minishift)

## What is provided
Once setup for development, it is possible to almost fully emulate a production deployment:
* OpenShift accessible via:
  * Web UI
  * `oc` command for CLI access
* Shepherd accessible via Web UI provides:
  * Deploy new Drupal or Wordpress sites
  * Add/Remove environments
  * Promote/Demote environments from their production url
  * Environments accessible directly regardless of dev/uat/prod status
  * Instant terminal & logs of any running environment
  * Backup and restore environments

Not similar to a production deployment:
* No real backing store for the PV's
* No front end router providing real traffic direction

## Prerequisites

### All systems
* Either [Minishift](https://www.openshift.org/minishift/) virtual machine - which
  provides an OpenShift cluster for local development or
* [OpenShift](https://github.com/openshift/origin/blob/release-3.11/docs/cluster_up_down.md)
  running in docker - rather than in a virtual machine.
* [oc](https://github.com/openshift/origin/releases) command line tool enables
  interaction with the OpenShift cluster.
* PHP 7+
* [Composer](https://getcomposer.org/)
* [Docker](https://www.docker.com/)
* An ssh key for builds that is *not* password protected.

### macOS
Additional tools are required for development on `macOS`. `docker for mac`
will work and the `./dsh` script has built in support for nfs setup for mac.
* [Docker for Mac](https://www.docker.com/docker-mac)

### Minishift configuration (recommended for macOS only)
Minishift provides an OpenShift environment that Shepherd can use to deploy sites.

Increase memory from the default 2048M & increase cpus to more than the default,
if you have spare cores.
```bash
minishift config set memory 4096
minishift config set cpus 4
```

Start Minishift, ready for the next step.
```bash
minishift start
```

Either add the Minishift oc command to your $PATH temporarily or
```bash
eval $(minishift oc-env)
```

Alternatively, permanently add oc command to /usr/local/bin that is packaged
with Minishift.
```bash
sudo ln -sf $(find ~/.minishift -name oc -type f | head -1) /usr/local/bin/
```

### OpenShift in Docker configuration (Linux only)
OpenShift itself can provide an environment that Shepherd can use to deploy sites.
OpenShift will directlly provide the Shepherd deployed sites on port 80/443.

This setup is considerably faster and the recommended development method, but
does require Linux and configuring a few things before running `./dsh`.
Putting these into ~/.bashrc is recommended for ongoing development.

* Set the OPENSHIFT_TYPE env var to something other than 'minishift'
  `export OPENSHIFT_TYPE=openshift`
* Set the domain for accessing shepherd to the ip of local docker
  `export DOMAIN=$(ip addr show docker0 | grep "inet\b" | awk '{print $2}' | cut -d/ -f1).nip.io`
* Start the oc cluster with `./openshift start`.
* Now run the ./dsh etc commands as per normal.

## Quickstart
Before proceeding, please configure either Minishift or OpenShift in Docker as above.
```bash
# Setup
composer install

# Bring up containers, runs any configuration tasks
./dsh start

# Drop into a utility shell ( this creates a ssh-agent container for macOS )
./dsh

# Build Shepherd
robo build
```

Login to the Shepherd UI with `xdg-open http://${DOMAIN}:8000`

Login to OpenShift Web UI with `xdg-open https://${OPENSHIFT}:8443` using the developer:developer credentials and
check that MariaDB is running before executing the next command.

```bash
robo dev:drupal-content-generate
robo dev:wordpress-content-generate
```

Follow the *Configure Velero* in [INSTALL.md](INSTALL.md) to set up Backup/Restore functionality.

Thats it; visit the OpenShift web interface to see a build running and a
deployment ready to occur when the build finishes.

For Minishift, the URL to the OpenShift web interface can be found in the
terminal log when Minishift starts. Alternatively, execute the following
command to open the Minishift console in a browser.

```bash
minishift console
```

## Repository details

The Shepherd repository uses Composers project functionality to provide the base repository layout.
There are some Composer plugins to make the create-project functionality work with Drupal:

* [Composer template for Drupal projects](https://github.com/drupal-composer/drupal-project)
* [Drupal scaffold](https://github.com/drupal-composer/drupal-scaffold)

Shepherd also has its own Composer plugins to further extend the create-project functionality:

* [Composer template for Shepherd Drupal projects](https://github.com/universityofadelaide/shepherd-drupal-project)
* [Shepherd Drupal scaffold](https://github.com/universityofadelaide/shepherd-drupal-scaffold)

Shepherd is sort of a monorepo as it has the base system, but the modules
are also made available separately for use by other projects:

* [Advantages of monolithic version control](https://danluu.com/monorepo/)
* [The Symfony Monolith Repository](https://www.youtube.com/watch?v=4w3-f6Xhvu8)
* [Git subtree splitter](https://github.com/splitsh/lite) - This is required
  to run the shepherd-module-update.sh script.
* [Shepherd modules](https://github.com/universityofadelaide/shepherd-modules)
* To use shepherd as an upstream repository for your own local deployment, see
  [Using this repository as an upstream](#Using this repository as an upstream)


### Development with Shepherd

As Shepherd is a monorepo, development is done using the git flow branching model
[A successful Git branching model](http://nvie.com/posts/a-successful-git-branching-model)
with the [git-flow](https://github.com/nvie/gitflow) Git extension.

### Working on the Shepherd modules

Development should proceed as normal, typically with:
* Start new git flow branch, commit, publish
  ```bash
  git flow feature start my-fantastic-feature
  git commit
  git flow feature publish
  ```
* Submit pull request through GitHub
* Merge into develop
* Update the shepherd-modules repo.
  ```bash
  ./shepherd-module-update.sh
  ```

Note: Only people with sufficient access can perform the last two steps.

## Using this repository as an upstream

Changes that are not UA specific should be done as Pull requests on the public repo
to minimise/avoid conflicts.

### Setup the repository
https://help.github.com/articles/configuring-a-remote-for-a-fork/

Basically setup a new repository on your own infrastructure, then add in the shepherd remote with:
```bash
git remote add shepherd https://github.com/universityofadelaide/shepherd.git
```

You should end up with something like:
```bash
origin      git@gitlab.adelaide.edu.au:web-team/ua-shepherd.git (fetch)
origin	    git@gitlab.adelaide.edu.au:web-team/ua-shepherd.git (push)
shepherd    https://github.com/universityofadelaide/shepherd.git (fetch)
shepherd    https://github.com/universityofadelaide/shepherd.git (push)
```

### Merge in changes from public shepherd repo
https://help.github.com/articles/syncing-a-fork/

git fetch shepherd -p
git merge shepherd/develop

As soon as you start adding things to your composer.json, then the composer.lock file
will start to give merge conflicts, and you will probably need to do:
```bash
git checkout --theirs composer.lock
```

Then you can finalise the merge and you're all caught up.
```bash
git commit -m"Merging changes in from upstream public repository."
```

## Working with Shepherd

### Exporting Drupal configuration
Shepherd has switched to using the PreviousNext drush cimy tools,
```bash
robo config:export-plus
```

### Configuring xdebug to run on the container ( CLI scripts )
The `PHP_IDE_CONFIG` var will be set automatically from the docker-compose.* file.

### Updating scaffolds
Anything using the `master` branch is going to be cached by composer. To unsure you
get the latest, clear the composer cache before running an update. There are some
custom scripts that run during the update, see the scripts section of composer.json
for more information.

```bash
composer clear-cache
composer update
```

To update packages using `composer update`, you will first need run
`composer install` - otherwise wikimedia/composer-merge-plugin will fail to
discover the OpenShift client dependency.

## Troubleshooting
- mysql host is `db`, database is `drupal`, user is `user` and password is `password`
- You may need to make `dsh` executable after a `composer update` - `chmod +x dsh`
- If performing a site install using `drush` ensure you have removed the
  `web/sites/default/files/` `web/sites/default/settings.php` and
  `web/sites/default/services.yml` files and checked permissions on the
  web/sites/default directory.
- `composer update` will NOT overwrite/update `/web/sites/default/settings.php`.
  if you require it to be updated, remove the file first and it will be re-created.
- If you receive a `connection refused` error when running `minishift start`
  the DNS settings out of the box for minishift are incorrect.
- Change to a working DNS server .. try google :
  ```bash
  minishift ssh "echo 'nameserver 8.8.8.8' | sudo tee /etc/resolv.conf"
  ```
- Test connection to API:
  ```bash
  curl --insecure -H "Authorization: Bearer $(oc login -u developer -p developer >/dev/null && oc whoami -t)" $(minishift console --url)/oapi/v1
  ```
- Updating OpenShift auth token in the dsh shell:
  ```bash
  ./dsh
  bin/drush -r /code/web cset shp_orchestration.openshift.openshift token ${NEW_TOKEN}
  ```

## Working with OpenShift

### Purging without destroying OpenShift itself
Purging all the example generated content on OpenShift. This removes everything with the example namespace that was generated with
the robo dev:drupal-content-generate command.
```bash
name=example; for type in is dc bc svc pvc route pods cronjobs jobs secrets; do for item in $(oc get "${type}" | grep ${name} | awk '{ print $1 }'); do oc delete ${type} ${item}; done; done
```

### Destroy and re-create OpenShift and ALL deployed test sites.

macOS
```bash
minishift delete
```

Linux
```bash
./openshift purge
```
