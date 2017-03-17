Shepherd
========

The University of Adelaide Shepherd provides an administration UI for provisioning web sites and managing user access.

## Requirements

* Docker ( macOS - docker for mac)
* PHP installed on your host machine
* Composer


```bash
# setup 
composer install
# bring up containers
docker compose up -d
# drop into a utility shell ( this creates a ssh-agent container for macOS )
./dsh shell
# bring down containers, -v to remove volumes
docker composer down -v
```

### Installing via Drush

```drush
./dsh shell
drush -r web si shepherd --db-url=mysql://user:password@db/shepherd --account-name=admin --account-pass=password --site-name=Shepherd
```

### Updating scaffolds 

Anything using the `master` branch is going to be cached by composer. To unsure you get the latest, clear the composer cache
```bash
composer clear-cache
# update - look at the composer.json > "scripts" to see the commands that are run during an update
composer update
```
### Additional Notes

- mysql host is `db`, database is `shepherd`, user is `user` and password is `password`
- you may need to make `dsh` executable after a `composer update` - `chmod +x dsh`
