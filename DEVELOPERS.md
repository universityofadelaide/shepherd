# Development setup

* [What is provided](#provided)
* [Prerequisites](#prerequisites)
* [Quickstart Guide](#quickstart)
* [Repository guide](#repository-details)
* [Shepherd development](#working-with-shepherd)
* [Troubleshooting](#troubleshooting)

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
* [Red Hat OpenShift Local](https://developers.redhat.com/products/openshift-local/overview)
* OpenShift Command Line Tool (oc) - Download from the local OpenShift portal site once provisioned.
* PHP 7+
* [Composer](https://getcomposer.org/)
* [Docker](https://www.docker.com/)
* An ssh key for builds that is *not* password protected.

### macOS
No longer supported for development.

### OpenShift in Code Ready Containers.
This is the only supported single node/local development environment for OpenShift
4.x. OpenShift will directly provide the Shepherd deployed sites on port 80/443.

This requires Linux and configuring a few things before running `./dsh`.
Putting these into ~/.bashrc is recommended for ongoing development.

* Start the Code Ready Containers cluster with `./openshift start`.
* Now run the ./dsh etc commands as per normal.

## Quickstart
Before proceeding, please configure either OpenShift with Code Ready Containers as above.
```bash
# Setup
composer install
# Alernatively.... Keep in mind permissions will need to be checked
docker run --rm --interactive -v ${HOME}/.ssh/id_rsa:/root/.ssh/id_rsa --tty --volume ${PWD}:/app composer install

# Bring up containers, runs any configuration tasks, drop into the shell.
./dsh

# Build Shepherd
robo build
```

Login to the Shepherd UI with `xdg-open http://shepherd.172.17.0.1:8000`

Login to OpenShift Web UI with `xdg-open https://console-openshift-console.apps-crc.testing` using the developer:developer credentials and
check that MariaDB is running before executing the next command.

```bash
robo dev:drupal-content-generate
robo dev:wordpress-content-generate
```

Follow the *Configure Backup/Restore operators* section in [INSTALL.md](INSTALL.md) to set up Backup/Restore functionality.

Thats it; visit the OpenShift web interface to see a build running and a
deployment ready to occur when the build finishes.

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

## Working with OpenShift

### Purging without destroying OpenShift itself
Purging all the example generated content on OpenShift. This removes everything with a name like 'node' or 'example' that that was generated with
the robo dev:drupal-content-generate command.
```bash
./oc_delete_all.sh
```

### Destroy and re-create OpenShift and ALL deployed test sites.

Linux
```bash
./openshift purge
```

## Troubleshooting
- mysql host is `db`, database is `drupal`, user is `user` and password is `password`
- You may need to make `dsh` executable after a `composer update` - `chmod +x dsh`
- If performing a site install using `drush` ensure you have removed the
  `web/sites/default/files/` `web/sites/default/settings.php` and
  `web/sites/default/services.yml` files and checked permissions on the
  web/sites/default directory.
- `composer update` will NOT overwrite/update `/web/sites/default/settings.php`.
  if you require it to be updated, remove the file first and it will be re-created.
- Test connection to API:
  ```bash
  curl --insecure -H "Authorization: Bearer $(oc login -u developer -p developer >/dev/null && oc whoami -t)" https://api.crc.testing:6443/apis/authorization.openshift.io/v1
  ```
- Updating OpenShift auth token in the dsh shell:
  ```bash
  ./dsh
  bin/drush -r /code/web cset shp_orchestration.openshift.openshift token ${NEW_TOKEN}
  ```
- If you get the following error when creating content: `An error occurred while communicating with OpenShift. cURL error 28: Failed to connect to 192.168.99.105 port 8443`

  This can be due to a network collision with other docker networks.
  Make sure all other containers are stopped, and then:
  ```bash
  docker network prune -f
  ```


### CRC Setup hint, maybe Ubuntu specific.

Any DNS related changes likely mean you need to restart everything.
```
./openshift purge
```

If this command doesn't work with crc:
```
$ host apps-crc.testing
Host apps-crc.testing not found: 3(NXDOMAIN)
```

dnsmasq might need manually configuring by creating a mycrc.conf file. Example commands.
```
$ echo "server=/apps-crc.testing/$(crc ip)" | sudo tee /etc/NetworkManager/dnsmasq.d/mycrc.conf
server=/apps-crc.testing/192.168.130.11
$ sudo systemctl restart NetworkManager
$ host apps-crc.testing
apps-crc.testing has address 192.168.130.11
```

If you see an error:
```
cURL error 6: Could not resolve host: api.crc.testing (see https://curl.haxx.se/libcurl/c/libcurl-errors.html)
```
You likely need to configure dnsmasq locally.
/etc/dnsmasq.d/local.conf 
```
address=/testing/192.168.130.11

# Google DNS - replace if you have your own DNS.
server=1.1.1.1
server=8.8.8.8

bind-interfaces
listen-address=172.17.0.1

no-negcache

cache-size=1000

log-queries 
```

Another reason for local DNS resolution failure may be a duplicate dnsmasq running in KVM.
```
virsh net-list
 Name   State    Autostart   Persistent
-----------------------------------------
 crc    active   yes         yes
```

If you see a duplicate called `default`, you can remove using:
```
virsh net-destroy default
```
You probably need to restart everything.

