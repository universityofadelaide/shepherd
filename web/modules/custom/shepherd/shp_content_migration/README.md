
## Shepherd content migration.

This module is designed to facilitate migrating from an existing Drupal 8 Shepherd instance,
likely connected to a 3.x cluster to a new Drupal 9 Shepherd instance on a 4.x cluster.

#### To run the migrations in full, checkout rerun_migration.sh in the root dir.

Example output.
```

```

#### To perform a specific node migration, use something like:
```
drush migrate:import shp_site --idlist=199:en
```

### Very handy information:

[How to migrate from Drupal 8 to Drupal 9](http://antrecu.com/blog/how-migrate-drupal-8-drupal-9)

This didn't work for me. Think we'd need to be using SqlBase directly.
[Optimize migration of specific source IDs for SQL sources](https://www.drupal.org/node/2780839)
