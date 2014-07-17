$script = <<SCRIPT
    echo Adjusting webroot symlink...
    rm -rf /var/www
    ln -s /vagrant/web /var/www
    service apache2 restart
SCRIPT

Vagrant.configure("2") do |config|
    config.vm.box = "ubuntu-lamp-2014-07-07"
    config.vm.box_url = "https://www.dropbox.com/s/r7awn51cpfef8gz/package-2014-07-07-php55.box?dl=1"
    config.vm.network :private_network, ip: "192.168.72.10"
    config.vm.hostname = "bookbox.local"
    config.vm.provision "shell", inline: $script

    config.vm.provider "virtualbox" do |v|
        v.name = "book-box"
        v.customize ["modifyvm", :id, "--memory", "650"]
        v.customize ["modifyvm", :id, "--cpus", "1"]
        v.customize ["modifyvm", :id, "--ioapic", "on"]
    end
end