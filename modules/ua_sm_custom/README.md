# University of Adelaide Site Manager Custom Module

Provides custom functionality for the UA Site Manager application.

## Relationship with LDAP

The UA Site Manager has a complex relationship with LDAP. LDAP is the source of editor authorisation for university websites - not to be confused with authentication, which is single sign-on, and provided by CAS. The Site Manager is the interface for configuring LDAP. The LDAP is a complex arrangement of Sites, Users and Roles. The Site Manager, and the Drupal websites themselves translate these into Permissions, which governs the abilities of a user.

* Site - A site is a single website defined by a domain and path - for example `www.adelaide.edu.au` and `/sciences`. The Site Manager stores a list of sites internally, and updates the LDAP when one is added or changes are made.
* User - A user is potentially any one with an 'a' number.
* Role - A role is a set of permissions that determine the access a user has to a site. For instance, the Author role will allow a user to create content, but not publish it live to the web.
* Permission - A permission is a single access right for a site. For example, 'create an article', or 'add a photo to a gallery'.

## LDAP Schema

The obvious LDAP schema for this situation would be to create an organisational unit (OU) for each site, then a bunch of entities under each site to represent roles. Assigning users as members of these roles would indicate ownership. However, this is not very performant when you want to ask queries like "what sites does user X have role Y for?".

For this reason, we elected to use a flat structure, which represents each site/role combination as a single entity. Users can be assigned as members of these entities, indicating they have the specified role. The site/role entities are given common names (CNs) with the format `<domain>#<path>#<role>`, for example `www.adelaide.edu.au#health#ua_editor`. Note that the path section is munged to remove special characters not allowed in LDAP names.

## Adding a Site

When a site is added, entities must be created for all site/role combinations in LDAP.

## Adding a Role

When a role is added, entities must be created for ALL SITES for the role. Obviously this is rather unideal. The list of roles should be considered semi-immutable.

## Adding a User to a Site/Role

A user can be added to a site/role entity by declaring them a unique member of it. This relationship is reflexively represented on the user by their being a

## Jenkins Build Server

Grab the oAuth token from the build triggers field:
https://jenkins.services.adelaide.edu.au/view/Drupal/job/deploy-drupal-site/configure

Set the path, token and job in the configuration.
