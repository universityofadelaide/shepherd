set :app_name,      "uasm"
set :location,      "uasm.drupalcode.com"
set :application,   "uasm.drupalcode.com"
set :user,          "deployer"
set :runner,        "deployer"
set :port,          22
set :default_stage, "dev"
set :use_sudo,      false

# This allow us to compile the styleguide and have it as part of the deploy.
# Instead of checking out the repo on the remote host, we build the deploy
# locally and then copy it over.
set :scm, :none
set :repository, "."
set :deploy_via, :copy
set :copy_exclude, [ ".git" ]

ssh_options[:forward_agent] = true

after "deploy:update_code", "robo:build"

set :robo_bin,  "robo"
set :mysql_user, "#{app_name}"
set :mysql_pass, "#{app_name}"
set :mysql_db,   "#{app_name}"
set :mysql_host, "localhost"
namespace :robo do
  task :build, :on_error => :continue do
    run "cd #{release_path} && #{robo_bin} build -Dmysql.queryString='mysql://#{mysql_user}:#{mysql_pass}@#{mysql_host}/#{mysql_db}'"
  end
end
