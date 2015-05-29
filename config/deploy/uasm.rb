set :deploy_to, "/var/www/uasm.ua.previousnext.com.au"
set :branch,    "develop"
role :app,      "uasm.ua.previousnext.com.au"
set :app_path,  "#{release_path}/app"

set :mysql_db, "ua_uasm"
