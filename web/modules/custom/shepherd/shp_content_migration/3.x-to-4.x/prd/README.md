
# This information is for automating the migration process.

## Setup
see SETUP.md

## Daily processing
put new domains & paths into the daily_sites.txt file.
Execute the process_sites.sh script.
Copy the output commands one at a time into the terminal to execute the migrations & backup/restores.

## Possible issues
* Site not picked up/in the process_sites.sh output
  Make sure there IS a trailing '/' on root sites, but NOT on sub-sites.
* CSS/JS snippets don't come across because the source version was too old.
  Fix by drush cex on the source, copy/paste into a file in an empty folder on the new pod and drush cim --source=/tmp/import.
* Site or Environment appears to not import
  If you get this sort of output, check that you haven't already imported it before.
   [notice] Processed 0 items (0 created, 0 updated, 0 failed, 0 ignored) - done with 'shp_site'
   [notice] The following specified IDs were not found in the source IDs: 11430:en.
  Its safe to run the drush mim step more than once, it wont update/re-import.
* Error when creating the backup or restore object
  Maybe this one has already been done? If re-doing, the existing backup/restore pod will need to be removed.
* Typoed the backup command?
  It will likely just fail, fix and re-run. might need to delete the pod.
* Typoed the restore command?
  Check it didn't accidentally restore to the wrong env, then fix and re-run, might need to delete the pod.

## Example session for uat:

```
$ ./process_sites.sh
Logged into "https://rhosd-console.services.adelaide.edu.au:8443" as "system:serviceaccount:shepherd-dev:shepherd" using the token provided.

You have one project on this server: "shepherd-dev"

Using project "shepherd-dev".
Already on project "shepherd-dev" on server "https://rhosd-console.services.adelaide.edu.au:8443".
Process for: mp-test-1.openshift.development.adelaide.edu.au/mp-test
./new_login.sh
oc rsh dc/ua-shepherd-dc drush mim shp_site --idlist=52:en
oc rsh dc/ua-shepherd-dc drush mim shp_environment --idlist=232:en
NEW_SITE=$(oc rsh dc/ua-shepherd-dc drush sqlq "SELECT destid1 FROM migrate_map_shp_site WHERE sourceid1 = 52;" | tr -d '\r')
NEW_ENV=$(oc rsh dc/ua-shepherd-dc drush sqlq "SELECT destid1 FROM migrate_map_shp_environment WHERE sourceid1 = 232;" | tr -d '\r')
./backup.sh 52 232 ${NEW_SITE} ${NEW_ENV}
Wait for backup to finish.
./restore.sh 52 232 ${NEW_SITE} ${NEW_ENV}
=======================================
Process for: batza-test-site-0.openshift.development.adelaide.edu.au/
./new_login.sh
oc rsh dc/ua-shepherd-dc drush mim shp_site --idlist=209:en
oc rsh dc/ua-shepherd-dc drush mim shp_environment --idlist=235:en
NEW_SITE=$(oc rsh dc/ua-shepherd-dc drush sqlq "SELECT destid1 FROM migrate_map_shp_site WHERE sourceid1 = 209;" | tr -d '\r')
NEW_ENV=$(oc rsh dc/ua-shepherd-dc drush sqlq "SELECT destid1 FROM migrate_map_shp_environment WHERE sourceid1 = 235;" | tr -d '\r')
./backup.sh 209 235 ${NEW_SITE} ${NEW_ENV}
Wait for backup to finish.
./restore.sh 209 235 ${NEW_SITE} ${NEW_ENV}
=======================================
Process for: mp-test-0.openshift.development.adelaide.edu.au/mp-test
./new_login.sh
oc rsh dc/ua-shepherd-dc drush mim shp_site --idlist=52:en
oc rsh dc/ua-shepherd-dc drush mim shp_environment --idlist=223:en
NEW_SITE=$(oc rsh dc/ua-shepherd-dc drush sqlq "SELECT destid1 FROM migrate_map_shp_site WHERE sourceid1 = 52;" | tr -d '\r')
NEW_ENV=$(oc rsh dc/ua-shepherd-dc drush sqlq "SELECT destid1 FROM migrate_map_shp_environment WHERE sourceid1 = 223;" | tr -d '\r')
./backup.sh 52 223 ${NEW_SITE} ${NEW_ENV}
Wait for backup to finish.
./restore.sh 52 223 ${NEW_SITE} ${NEW_ENV}
=======================================
```
