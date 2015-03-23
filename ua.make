; University of Adelaide site make file

; Core version
; The make file always begins by specifying the core version of Drupal for
; which each package must be compatible.
core = 8.x

; API version
; The make file must specify which Drush Make API version it uses.
api = 2

; Drupal core.
projects[drupal][download][type] = git
projects[drupal][download][url] = http://git.drupal.org/project/drupal.git
projects[drupal][download][tag] = 8.0.0-beta6

