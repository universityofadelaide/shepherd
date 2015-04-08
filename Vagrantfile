if File.exists?(File.expand_path('~/.aws/ua'))
  creds = File.read(File.expand_path('~/.aws/ua')).lines
  ENV['AWS_ACCESS_KEY_ID']     = creds[0].chomp
  ENV['AWS_SECRET_ACCESS_KEY'] = creds[1].chomp
end

Vagrant.configure("2") do |config|
  config.vm.define 'lamp', primary: true do |lamp|
    lamp.vm.box      = 'ua-lamp'
    lamp.vm.hostname = 'ua.dev'
    lamp.vm.box_url  = 'https://s3-ap-southeast-2.amazonaws.com/ua-boxes/lamp.box'

    # This script is a last chance for Developers to add more
    # configuration to the Vagrant host.
    #
    # Examples:
    #  * Create files directories
    #  * Setup additional databases
    lamp.vm.provision :shell, path: "provision.sh"
  end
end
