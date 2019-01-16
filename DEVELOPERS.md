# Development setup

* [Prerequisites](#prerequisites)
* [Quickstart Guide](#quickstart)
* [Repository guide](#repository-details)
* [Shepherd development](#working-with-shepherd)
* [Troubleshooting](#troubleshooting)
* [Using Minishift](#working-with-minishift)

## Prerequisites

### All systems
* [Minishift](https://www.openshift.org/minishift/) virtual machine - which
provides an OpenShift cluster for local development or
* [Openshift](https://github.com/openshift/origin/blob/master/docs/cluster_up_down.md) running in
docker - rather than in a virtual machine. 
* [oc](https://github.com/openshift/origin/releases) command line tool enables
interaction with the OpenShift cluster.
* PHP 7+
* [Composer](https://getcomposer.org/)
* [Docker](https://www.docker.com/)
* dnsmasq
* An ssh key for builds that is *not* password protected.

### macOS
Additional tools are required for development on `macOS`. As of writing `docker toolbox` is supported. `docker for mac` will work but due to [issues with filesystem speed](https://github.com/docker/for-mac/issues/77) you will need to install a nfs mount tool. This is not automatically configured in the `dsh` script whereas `docker toolbox` is. 

* [Docker Toolbox](https://www.docker.com/products/docker-toolbox) or [Docker
for Mac](https://www.docker.com/docker-mac)
* [docker-machine-nfs](https://github.com/adlogix/docker-machine-nfs)
* [d4m nfs](https://github.com/IFSight/d4m-nfs) - Optional for `docker for mac` users. Please note that the `dsh` script doesn't automate the `nfs` mounts for you, this is a manual process.

### Minishift configuration
Minishift provides an OpenShift environment that Shepherd uses to deploy sites.

Minishift can use multiple virtualisation backends. On Linux we recommend using
Virtualbox, but KVM is fine. On macOS the default is xhyve which is fine,
though you may wish to use Virtualbox if you're running Docker Toolbox.

```bash
# Increase memory from the default 2048M.
minishift config set memory 4096
# Increase cpus to more than the default 2, if you have spare cores.
minishift config set cpus 4
```

```bash
# On Linux, change default vm-driver to Virtualbox.
minishift config set vm-driver virtualbox
```

If you are working with multiple OpenShift clusters using the oc tool, add the
Minishift oc command to your $PATH.
```bash
eval $(minishift oc-env)
```

Alternatively, permanently add oc command to /usr/local/bin that is packaged
with Minishift.
```bash
sudo ln -sf $(find ~/.minishift -name oc -type f | head -1) /usr/local/bin/
```

### OpenShift in Docker configuration (Linux only)
Optionally you can run OpenShift locally with `docker` on your host machine by configuring a few things before running `./dsh`
* Install MariaDB on your localhost, or in a docker container listening on 3306.
* Ensure that mysql is listening on all IP's
* Grant root access to any host
* Set the OPENSHIFT_TYPE env var to something other than 'minishift'
  `export OPENSHIFT_TYPE=docker`
* Set the domain for accessing shepherd to {docker0 IPv4 address}.nip.io
  `export DOMAIN=172.17.0.1.nip.io`
* Start the oc cluster `oc cluster up` see `oc cluster up -h` for advanced config like persistent storage and config.
* Set nginx to run on 8080, as OpenShift uses port 80 `docker rm -f nginx-proxy`
`docker run -d -p 8080:80 -v /var/run/docker.sock:/tmp/docker.sock:ro --restart always --name nginx-proxy jwilder/nginx-proxy`
* Now run the ./dsh etc commands as per normal.
* Shepherd will appear on port 8080


## Quickstart 

```
# macOS installation script
# This will ensure that all deps for docker toolbox/machine development are installed correctly.
./dsh install_tools

# Setup
composer install

# Bring up containers, runs any configuration tasks
./dsh start

# Configure dsnmasq
./dsh setup_dnsmasq

# Drop into a utility shell ( this creates a ssh-agent container for macOS )
./dsh shell

# Build Shepherd
robo build
robo config:import-plus
drush cr
```

Login to OpenShift Web UI using the developer:developer credentials and
check that MariaDB is running before executing the next command.

```bash
robo dev:drupal-content-generate
```

Thats it; visit the OpenShift web interface to see a build running and a
deployment ready to occur when the build finishes. The URL to the web interface
can be found in the terminal log when Minishift starts. Alternatively, execute
the following command to open the Minishift console in a browser.

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
* ```
  git flow feature start my-fantastic-feature
  git commit
  git flow feature publish
  ```
* Submit pull request through GitHub
* Merge into develop
* Update the shepherd-modules repo.
  ```
  ./shepherd-module-update.sh
  ```

Note: Only people with sufficient access can perform the last two steps.

## Using this repository as an upstream

Changes that are not UA specific should be done as Pull requests on the public repo
to minimise/avoid conflicts.

### Setup the repository
https://help.github.com/articles/configuring-a-remote-for-a-fork/

Basically setup a new repository on your own infrastructure, then add in the shepherd remote with:
```
git remote add shepherd https://github.com/universityofadelaide/shepherd.git
```

You should end up with something like:

```
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

```
git checkout --theirs composer.lock
```

Then you can finalise the merge and you're all caught up.

```
git commit -m"Merging changes in from upstream public repository."
```

## Working with Shepherd

### Exporting Drupal configuration
When exporting config always to remember to clean the `yml` files of `uuid` and config hashes.

```bash
# You can run this in the container.
# Replace {config_dir} with the destination you exported config to.
sed -i '/^uuid: .*$/d' /code/web/{config_dir}*.yml
```

```bash
# Regex to replace config hashes:
# case insensitive and multi-line
/\_core\:.*\n.*default\_config\_hash:.*$/im
```

To diff the configuration :
```bash
# you can run this on the container
diff -N -I "   - 'file:.*" -qbr {old_config_path} {new_config_path}
```

### Configuring xdebug to run on the container ( CLI scripts )

```bash
export PHP_IDE_CONFIG="serverName=shepherd.test"
```

### Updating scaffolds
Anything using the `master` branch is going to be cached by composer. To unsure you get the latest, clear the composer cache
```bash
composer clear-cache
# update - look at the composer.json > "scripts" to see the commands that are run during an update
composer update
```

To update packages using `composer update`, you will first need run
`composer install` - otherwise wikimedia/composer-merge-plugin will fail to
discover the OpenShift client dependency.

## Troubleshooting
- Shepherd assumes dnsmasq is set to the `test` domain by default.
- mysql host is `db`, database is `drupal`, user is `user` and password is `password`
- You may need to make `dsh` executable after a `composer update` - `chmod +x dsh`
- If performing a site install using `drush` ensure you have removed the `web/sites/default/files/` `web/sites/default/settings.php` and
`web/sites/default/services.yml`
- Purging doesn't remove the `ssh_agent` or `nginx_proxy` there will be some volumes that will continue living. You will need to
occasionally remove these : `docker volume ls` and `docker volume rm ${vol_name}` - to remove dangling volumes.
- Updating `/web/sites/default/settings.php` using `composer update` requires you to remove the file before.
- If you receive a `connection refused` error when running `minishift start` the DNS settings out of the box for minishift are incorrect.
- Change to a working DNS server .. try google :
```bash
  minishift ssh "echo 'nameserver 8.8.8.8' | sudo tee /etc/resolv.conf"
```
- Test connection to API:
```bash
curl --insecure -H "Authorization: Bearer $(oc login -u developer -p developer >/dev/null && oc whoami -t)" $(minishift console --url)/oapi/v1
```
- Updating OpenShift auth token:
```bash
# From utility container (dsh shell) :
bin/drush -r /code/web cset shp_orchestration.openshift.openshift token ${NEW_TOKEN}
```

## Working with Minishift

Purging all the example generated content on OpenShift. This removes everything with the example namespace that was generated with 
the robo dev:drupal-content-generate command.
```bash
name=example; for type in is dc bc svc pvc route pods cronjobs jobs secrets; do for item in $(oc get "${type}" | grep ${name} | awk '{ print $1 }'); do oc delete ${type} ${item}; done; done
```
