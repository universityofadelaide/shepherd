set :deploy_to, "/var/www/uasm.drupalcode.com"
set :branch,    "develop"
role :app,      "uasm.drupalcode.com"
set :app_path,  "#{release_path}/app"

set :mysql_db, "ua_uasm"
