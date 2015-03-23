Vagrant.configure("2") do |config|
  config.vm.define 'lamp', primary: true do |lamp|
    lamp.vm.box      = 'ua-lamp'
    lamp.vm.hostname = 'ua.dev'
    lamp.vm.box_url  = '/Users/nick/projects/pnx/ua-dev/packer/boxes/lamp.box'

    # This script is a last chance for Developers to add more
    # configuration to the Vagrant host.
    #
    # Examples:
    #  * Create files directories
    #  * Setup additional databases
    lamp.vm.provision :shell, path: "provision.sh"
  end
end

