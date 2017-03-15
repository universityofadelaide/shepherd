
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
# bring down containers, -v to remove volumes
docker composer down -v
```

### Additional Notes

- mysql host is `db`
