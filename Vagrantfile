Vagrant.configure("2") do |config|
  config.vm.define 'uasm', primary: true do |uasm|
    uasm.vm.box      = 'ua-lamp'
    uasm.vm.hostname = 'uasm.dev'
    uasm.vm.box_url  = 'http://wcms-files.adelaide.edu.au/ua-lamp.box'

    # This script is a last chance for Developers to add more
    # configuration to the Vagrant host.
    #
    # Examples:
    #  * Create files directories
    #  * Setup additional databases
    uasm.vm.provision :shell, path: "provision.sh"
  end
end

