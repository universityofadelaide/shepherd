UA Site Manager
===============

The University of Adelaide Site Manager. Provides an administration UI for provisioning web sites and managing user access.

## Requirements

* Vagrant 1.6+ (+ Plugins) - http://docs.vagrantup.com/v2/installation
* Virtualbox - https://www.virtualbox.org/wiki/Downloads

**Install Vagrant plugins**

Run the following via the command line:

```bash
# Virtualbox support.
$ vagrant plugin install vagrant-vbguest

# Automatically assigns an IP address.
$ vagrant plugin install vagrant-auto_network

# Adds "/etc/hosts" (local DNS) records.
$ vagrant plugin install vagrant-hostsupdater
```

## Getting started

**1) Start the VM**

```bash
$ vagrant up
```

All commands from here are to be run within the VM. This can be done via the command:

```bash
$ vagrant ssh
```

This will take you to the root of the project **inside** of the vm.

**2) Run the dev init script**

```bash
$ robo dev:init
```

This will ask you your name and email address, which is needed for Git. It will also give you an opportunity to generate an SSH key that you can add to your Github or Gitlab account to enable interaction with git without manual authentication. If you already have a keypair you want to use, you can ignore this.

**3) Build the project**

```bash
$ robo build
```

For a list of tasks that can be run:

```bash
$ robo -l
```

**4) Go to the site**

The site can be found on the domain: `http://uasm.dev`

This address is configurable in the Vagrantfile - feel free to change it to whatever suits your project.

## Updating VM

If a new version of the Vagrant box is available you can run the following:

```bash
  $ vagrant halt
  $ vagrant box remove ua-lamp
  $ vagrant destroy -f && vagrant up
```

## Deploy

**Note: These commands are also covered in the .pnxci.yml file**

### Setup

* Bundler is used to install ruby gems.
* Capistrano (Ruby Gem) is leveraged for deployments.

```bash
$ bundle install --path vendor/bundle
```

### Deploy QA

```bash
$ bundle exec cap dev deploy
```

### Deploy Staging

```bash
$ bundle exec cap staging deploy
```

## Anatomy of the project

### Directories

* **app** - This is the Drupal root directory, which is clones from Drupal's git repository by the Robo build system. This gets deleted every time you run `robo build`.
* **bin** - The tools installed by Composer and other technologies. You can access these tools globally within the Vagrant machine (it's automatically added to $PATH).
* **build** - Testing stuff eg. PHPCS reporting.
* **modules** - Custom modules. This is symlinked into by the **app/modules/custom** directory. Put any custom code in here that doesn't make sense being its own stand-alone module. If it does, make a seperate git repository for it and add it to the `ua-site-manager.make.yml` so it gets added during build time - that way other UA Drupal developers can use it.
* **themes** - Custom themes. This is symlinked into by the **app/themes/custom** directory.
* **vendor** - This is the code that goes along with the **bin** directories tools.

### Files

* **.pnxci.yml** - The CI and CD build system file. This file gets run at the time for testing and deployment.
* **RoboFile.php** - The Robo build file. This declares all the build steps for the project. Note: Robo recently replaced Phing as our build tool, which used a **build.xml** file. Many other PHP projects may still use this format, but we chose to move away from it because XML.
* **composer.json** - Project dependencies. This is not where you put external libraries you want Drupal to have access to - they go in other composer.json files in modules, which are parsed by the Composer Manager module. See **app/modules/contrib/composer_manager/README.txt** for more details.
* **provision.sh** - Additional steps that can be taken to provision the Vagrant VM. If there's something you keep doing every time you do a `robo build`, add it to here instead.
* **ua-site-manager.make** - The project's Drush make file. This locks in versions (especially Drupal core).
* **Vagrantfile** - The Vagrant VM's configuration.
