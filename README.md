# Shepherd

[![Build Status](https://travis-ci.org/universityofadelaide/shepherd.svg?branch=feature%2Fget-ci-phpunit-working)](https://travis-ci.org/universityofadelaide/shepherd)
[![License](https://img.shields.io/github/license/universityofadelaide/shepherd.svg)](LICENSE)

Shepherd is a web based administration tool for web sites using the 
[OpenShift](https://www.openshift.com/) Container Platform.

The development environment for Shepherd requires an instance of the Shepherd 
Drupal app running in Docker on your host machine and either:

* [Minishift](https://www.openshift.org/minishift/) virtual machine - which
provides an OpenShift cluster for local development or
* [Openshift](https://github.com/openshift/origin/blob/master/docs/cluster_up_down.md) running in
docker - rather than in a virtual machine. 

The [oc](https://github.com/openshift/origin/releases) command line tool enables
interaction with the OpenShift cluster.

[Installation](INSTALL.md)

[Developer documentation](DEVELOPERS.md)
