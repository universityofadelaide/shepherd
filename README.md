Shepherd
========

The University of Adelaide Shepherd provides an administration UI for provisioning web sites and managing user access. 

## Requirements

* [Docker](https://www.docker.com/community-edition) ( macOS - docker for mac)
* PHP installed on your host machine
* [Composer](https://getcomposer.org/)
* [Minishift](https://github.com/minishift/minishift) - for Orchestration tasks.


```bash
# setup 
composer install

# bring up containers
docker compose up -d

# Configure dsnmasq
./dsh dnsmasq_setup

# Drop into a utility shell ( this creates a ssh-agent container for macOS )
./dsh shell

# bring down containers, -v to remove volumes
docker composer down -v
```

### Installing via Drush

```drush
# Drop into the utility shell
./dsh shell
# Run the drush site install
# set user:password
drush -r web si shepherd --db-url=mysql://user:password@db/shepherd --account-name=admin --account-pass=password --site-name=Shepherd -y
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

### Setting up Minishift orchestration

Documentation can be found on [Google Drive](https://docs.google.com/document/d/1ZeypugCthqFfHiLXCe6XyEJ9kO0rWhTzLb3bQa3KLqY/edit#heading=h.e4erzx509ekv)

Once you have configured your minishift openshift environment do the following : 
- Go to the orchestration settings in the configuration page. 
- Select openshift as the provider. 
- Select the openshift tab and update the details.

Test the API configuration by triggering an environment by editing an existing site definition.


### Additional notes

- mysql host is `db`, database is `shepherd`, user is `user` and password is `password`
- You may need to make `dsh` executable after a `composer update` - `chmod +x dsh`
- If performing a site install using `drush` ensure you have removed the `web/sites/default/files/` `web/sites/default/settings.php` and 
`web/sites/default/services.yml`
