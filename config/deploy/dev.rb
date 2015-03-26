set :deploy_to, "/var/www/qa.ua.previousnext.com.au"
set :branch,    "master"
role :app,      "qa.ua.previousnext.com.au"
set :app_path,  "#{release_path}/app"

set :mysql_db, "ua_qa"
