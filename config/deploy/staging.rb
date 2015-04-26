set :deploy_to, "/var/www/staging.ua.previousnext.com.au"
set :branch,    "releases"
role :app,      "staging.ua.previousnext.com.au"
set :app_path,  "#{release_path}/app"

set :mysql_db, "ua_staging"
