# Development

## Prerequisites

### All systems
* PHP 7+
* [Composer](https://getcomposer.org/)
* [Docker](https://www.docker.com/)
* dnsmasq 
* [VirtualBox](https://www.virtualbox.org/wiki/Downloads)
* [Minishift](https://github.com/minishift/minishift/releases) - 1.0.0+
* An ssh key for builds that is *not* password protected.

### macOS
* [Docker Toolbox](https://www.docker.com/products/docker-toolbox) or [Docker
for Mac](https://www.docker.com/docker-mac)
* [docker-machine-nfs](https://github.com/adlogix/docker-machine-nfs)

## Local installation quickstart
Ensure you have a fresh Minishift instance (`minishift delete`).
```bash
./dsh
robo build
```

Login to OpenShift Web UI using the developer:developer credentials and 
check that MariaDB is running before executing the next command.

```bash
robo dev:content-generate
```

Thats it; visit the OpenShift web interface to see a build running and a 
deployment ready to occur when the build finishes. The URL to the web interface
can be found in the terminal log when Minishift starts. Alternatively, execute
the following command to open the Minishift console in a browser.

```bash
minishift console
```

### Minishift configuration
Minishift provides an OpenShift environment that Shepherd uses to deploy sites.

Minishift can use multiple virtualisation backends. On Linux we recommend using
Virtualbox, but KVM is fine. On macOS the default is xhyve which is fine,
though you may wish to use Virtualbox if you're running Docker Toolbox.

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

### OpenShift in Docker configuration
Minishift is really just another layer that you don't need, you can run openshift locally with just 
a couple of tweaks before running ./dsh
* Install mysql on your local host, or in a docker container listening on 3306 (but it wont work within openshift as shepherd can't talk to it easily).
* Ensure that mysql is listening on all IP's
* Grant root access to any host
* Set the OPENSHIFT_TYPE env var to something other than 'minishift'
* Start the oc cluster `oc cluster up` see `oc cluster up -h` for advanced config like persistent storage and config.
* Set nginx to run on 8080, as openshift uses port 80 `docker rm -f nginx-proxy`
`docker run -d -p 8080:80 -v /var/run/docker.sock:/tmp/docker.sock:ro --restart always --name nginx-proxy jwilder/nginx-proxy`
* Now run the ./dsh etc commands as per normal.
* Shepherd will appear on port 8080

## Working with Shepherd

### Installing SwaggerUI for developing with OpenShift API
```bash
# run the swagger ui container
docker run -d -p 8000:8080 swaggerapi/swagger-ui
```
	
then visit `http://swagger.test:8000` and paste 
`http://home.caseyfulton.com/openshift-openapi-spec.json` into the box at the
top and hit explore.

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
bin/drush -r web cset shp_orchestration.openshift.openshift token ${NEW_TOKEN}
```


