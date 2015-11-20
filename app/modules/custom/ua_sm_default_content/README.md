# University of Adelaide Site Manager Custom Module

Provides a set of default content for dev and test. After doing a `build` or `build:install`, just enable the ua_sm_default_content module to get some base content. Alternatively, just run the command `drush en ua_sm_default_content`.

WARNING: DO NOT ADD TO INSTALL PROFILE. The custom types cause this to break site install if the module is enabled during site install.

Feel free to add more content by exporting it with the drush command `drush dce <entity type> <id> > nn_output_file.json`. The number at the start of the output file name determines import order, which may be important if the thing you're exporting has entity references to already existent entities. Almost everything is a node, so this is usually something like `drush dce node 12 > 12_some_site_instance.json`.
