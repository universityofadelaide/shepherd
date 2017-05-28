Shepherd
========

The University of Adelaide Shepherd provides an administration UI for
provisioning web sites and managing user access built on OpenShift.

The suggested development environment for Shepherd requires both an instance of
the Drupal app itself running in Docker and OpenShift running in a virtual
machine provided by the [Minishift](https://www.openshift.org/minishift/)
command line tool.

## Developing Shepherd - common tasks
First, output the developer token for openshift.
```bash
oc login -udeveloper -pdeveloper && oc whoami -t
```

Now start up the shepherd environment and configure it.
```bash
./dsh
robo build
TOKEN=output_from_oc_whoami_-t bin/drush -r web scr ShepherdContentGenerate.php --uri=shepherd.test
```

Thats it, there should already be a build running, and a deployment ready to occur when the
build finishes.


## Getting started for Shepherd development
The following prerequisites must be installed.

### All systems
* PHP 7+
* [Composer](https://getcomposer.org/)
* [Docker](https://www.docker.com/)
* dnsmasq
* [VirtualBox](https://www.virtualbox.org/wiki/Downloads)
* [Minishift](https://github.com/minishift/minishift/releases) - 1.x.x

### macOS
* [Docker Toolbox](https://www.docker.com/products/docker-toolbox) or [Docker
for Mac](https://www.docker.com/docker-mac)
* [docker-machine-nfs](https://github.com/adlogix/docker-machine-nfs)

### Starting Minishift
Minishift provides an OpenShift environment that Shepherd uses to deploy sites.

Minishift can use multiple virtualisation backends. On Linux we recommend using
virtualbox due to stability issues with kvm. On macOS the default is xhyve which
is fine, though you may wish to use virtualbox if you're running Docker Toolbox.

*NOTE:* Ensure you are running 1.x.x of `minishift`, beta versions 1.x do not have the `oc-env` commands.

```bash
# On Linux, change default vm-driver to virtualbox.
minishift config set vm-driver virtualbox
```

Start Minishift and add an SSH key to it for authentication against private git
repositories. The key must have no password and have sufficient access.
```bash
minishift start
```

Add the oc command to your $PATH.
```bash
eval $(minishift oc-env)
```

Alternatively, permanently add oc to /usr/local/bin.
```bash
sudo ln -sf $(find ~/.minishift -name oc -type f | tail -1) /usr/local/bin/
```

Create a secret to use to build with (needs password less key)
```bash
oc secrets new-sshauth build-key --ssh-privatekey=${HOME}/.ssh/id_rsa
```

#### Troubleshooting Minishift
If you receive a `connection refused` error when running `minishift start` the DNS settings out of the box for minishift are incorrect.
Change to a working DNS server .. try google :
```bash
  minishift ssh "echo 'nameserver 8.8.8.8' | sudo tee /etc/resolv.conf"
```

### Starting Shepherd
```bash
composer install
# Make dsh executable.
chmod +x ./dsh
./dsh install_tools (macOS only)
./dsh setup_dnsmasq (Linux only)
./dsh start
```

### Perform site install on Shepherd
Visit http://shepherd.test/core/install.php and complete the install wizard.

### Configure Shepherd to use local Minishift
Create a mysql database in OpenShift for deployments to use:
```bash
oc new-app mysql MYSQL_USER=shepherd MYSQL_PASSWORD=shepherd MYSQL_DATABASE=shepherd -l db=shepherd
```

Visit http://shepherd.test/admin/config/system/shepherd/orchestration/provider_settings
and enter the following details:

Endpoint (API base url):
```bash
minishift console --url
```

Token (for authenticating against API):
```bash
oc login -u developer -p developer >/dev/null && oc whoami -t
```

* NOTE * The token expires every 24 hours. After this time you will need to re-login and generate a new token. 


Environment mode: dev.

Namespace (project name, default to myproject):
```bash
oc project -q
```

### Test connection to API:
```bash
curl --insecure -H "Authorization: Bearer $(oc login -u developer -p developer >/dev/null && oc whoami -t)" $(minishift console --url)/oapi/v1
```

### Updating token 

```bash
# from utility container : 
bin/drush -r web cset shp_orchestration.openshift.openshift token ${NEW_TOKEN}
```


### Installing SwaggerUI for developing with OpenShift API

```bash
# run the swagger ui container
docker run -d -p 8000:8080 swaggerapi/swagger-ui
```
	
then visit `http://swagger.test:8000` and paste 
`http://home.caseyfulton.com/openshift-openapi-spec.json` into the box at the top and hit explore

#### NOTE 
This is assuming you have setup dnsmasq to the `test` domain.


## Requirements

#### ATTN: macOS users
You can use Docker Toolbox or Docker for Mac with this project. Both are supported in the `dsh` script. Both environments
have [issues with filesystem speed](https://github.com/docker/for-mac/issues/77). Docker Toolbox ( `docker-machine` ) is using `docker-machine-nfs` to address the issue by using NFS.
This has improved speed massively for users. A similar solution for `docker for mac` called [d4m nfs](https://github.com/IFSight/d4m-nfs) is available and essentially does the same
thing, however this has not been implemented in the `dsh` script. If using `docker for mac` you will experience speed and peformance issues
performing tasks large writes inside the container.


* [Docker](https://www.docker.com/community-edition) ( **optional** macOS - docker for mac)
* [Docker Toolbox](https://www.docker.com/products/docker-toolbox) - ( macOS only )
* PHP installed on your host machine
* [Composer](https://getcomposer.org/)
* [Minishift](https://github.com/minishift/minishift) - for Orchestration tasks.


```bash
# macOS installation script
# this will ensure that all deps for docker toolbox/machine development are installed.
./dsh install_tools

# setup
composer install

# bring up containers, runs any configuration tasks
./dsh start

# Configure dsnmasq
./dsh setup_dnsmasq

# Drop into a utility shell ( this creates a ssh-agent container for macOS )
./dsh shell

# bring down containers, remove network and volumes.
./dsh purge
```

### Installing via Drush
```drush
# Drop into the utility shell
./dsh shell
# Run the drush site install
# set user:password
drush -r web si shepherd --db-url=mysql://user:password@db/drupal --account-name=admin --account-pass=password --site-name=Shepherd -y
# Remove previous install settings and files {from host}
sudo rm -rf web/sites/default/files/
sudo rm -f web/sites/default/settings.php
sudo rm -f web/sites/default/services.yml
```

### Updating scaffolds
Anything using the `master` branch is going to be cached by composer. To unsure you get the latest, clear the composer cache
```bash
composer clear-cache
# update - look at the composer.json > "scripts" to see the commands that are run during an update
composer update
```

### Additional notes and troubleshooting
- mysql host is `db`, database is `drupal`, user is `user` and password is `password`
- You may need to make `dsh` executable after a `composer update` - `chmod +x dsh`
- If performing a site install using `drush` ensure you have removed the `web/sites/default/files/` `web/sites/default/settings.php` and
`web/sites/default/services.yml`
- Purging doesn't remove the `ssh_agent` or `nginx_proxy` there will be some volumes that will continue living. You will need to
occasionally remove these : `docker volume ls` and `docker volume rm ${vol_name}` - to remove dangling volumes.
- Updating `/web/sites/default/settings.php` using `composer update` requires you to remove the file before.

#### Exporting configuration
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

#### Configuring xdebug to run on the container ( CLI scripts )

```bash
export PHP_IDE_CONFIG="serverName=shepherd.test"
```

## Deploying shepherd Directly via the OpenShift ui (not for development).
Login as admin and Import the shepherd OpenShift deployment template globally
```bash
oc login -u system:admin
oc create -f shepherd-openshift.yaml -n openshift
```
You can now click Add to project in the OpenShift ui to deploy shepherd directly.
