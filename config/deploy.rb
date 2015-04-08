set :app_name,      "ua"
set :location,      "qa.ua.previousnext.com.au"
set :application,   "qa.ua.previousnext.com.au"
set :scm,           :git
set :repository,    "git@github.com:previousnext/#{app_name}.git"
set :user,          "deployer"
set :runner,        "deployer"
set :branch,        "master"
set :port,          22
set :default_stage, "dev"
set :use_sudo,      false

# This allow us to compile the styleguide and have it as part of the deploy.
# Instead of checking out the repo on the remote host, we build the deploy
# locally and then copy it over.
set :deploy_via, :copy

ssh_options[:forward_agent] = true

after "deploy:update_code", "composer:install", "phing:build"

set :composer_bin, "composer"
namespace :composer do
  task :install do
    run "cd #{release_path} && #{composer_bin} install --prefer-dist --no-progress"
  end
end

set :phing_bin,  "bin/phing"
set :mysql_user, "#{app_name}"
set :mysql_pass, "#{app_name}"
set :mysql_db,   "#{app_name}"
set :mysql_host, "localhost"
namespace :phing do
  task :build, :on_error => :continue do
    run "cd #{release_path} && #{phing_bin} build -Dmysql.queryString='mysql://#{mysql_user}:#{mysql_pass}@#{mysql_host}/#{mysql_db}' styleguide:link"
  end
end
