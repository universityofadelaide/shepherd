
## Prerequisites

Setup the s2i image and deployment key as per - http://gitlab.adelaide.edu.au/web-team/s2i-shepherd-drupal/OPENSHIFT_QUICKSTART.md

## Quickstart

* Login as admin and Import the shepherd openshift deployment config globally
```
oc login -u system:admin
oc create -f shepherd-openshift.yaml -n openshift
```
* You can now deploy shepherd from the openshift ui.
