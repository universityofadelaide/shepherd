
UA Site Manager
===============

The University of Adelaide Site Manager provides an administration UI for provisioning web sites and managing user access.

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
