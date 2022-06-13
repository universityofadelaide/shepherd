# Shepherd

[![Build Status](https://travis-ci.org/universityofadelaide/shepherd.svg?branch=develop)](https://travis-ci.org/universityofadelaide/shepherd)
[![License](https://img.shields.io/github/license/universityofadelaide/shepherd.svg)](LICENSE)

Shepherd is a web based administration tool for web sites using the
[OpenShift](https://www.openshift.com/) Container Platform.

## Getting started

For using shepherd : [Installation](INSTALL.md)

For developing shepherd : [Developer documentation](DEVELOPERS.md)

## Contributing to Shepherd

Want to get involved ? Contributions are always welcome. Check the [Contributing guide](CONTRIBUTING.md) for information on how to get involved with the development of Shepherd.


### CRC Setup hint, maybe Ubuntu specific.
If this command doesn't work with crc:
```
$ host apps-crc.testing
Host apps-crc.testing not found: 3(NXDOMAIN)
```

dnsmasq might need manually configuring by creating a mycrc.conf file. Example commands.
```
$ echo "server=/apps-crc.testing/$(crc ip)" | sudo tee /etc/NetworkManager/dnsmasq.d/mycrc.conf
server=/apps-crc.testing/192.168.130.11
$ sudo systemctl restart NetworkManager
$ host apps-crc.testing
apps-crc.testing has address 192.168.130.11
