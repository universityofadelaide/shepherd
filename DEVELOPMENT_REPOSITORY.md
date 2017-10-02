# Development repository

## Details

The shepherd project is based on the Drupal scaffold system, and tries
to use best practises where possible. It pulls together a couple of 
techniques from the Drupal world including:

* [Composer template for Drupal projects](https://github.com/drupal-composer/drupal-project)
* [Drupal scaffold](https://github.com/drupal-composer/drupal-scaffold)

Of which Shepherd has its own specific versions:

* [Composer template for Shepherd Drupal projects](https://github.com/universityofadelaide/shepherd-drupal-project)
* [Shepherd Drupal scaffold](https://github.com/universityofadelaide/shepherd-drupal-scaffold)

Shepherd is sort of a monorepo as it has the base system, but the modules
are also made available separately for use by other projects:

* [Advantages of monolithic version control](https://danluu.com/monorepo/)
* [The Symfony Monolith Repository](https://www.youtube.com/watch?v=4w3-f6Xhvu8)
* [Git subtree splitter](https://github.com/splitsh/lite)
* [Shepherd modules](https://github.com/universityofadelaide/shepherd-modules)


## Development with Shepherd

As Shepherd is a monorepo, development is fairly straightforward, the only
additional thing that needs to be done is that the shepherd-module-update
script needs to be run.  

This script automatically performs the required magic to update the Shepherd modules
repository automatically from the Shepherd repo.
