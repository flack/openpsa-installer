OpenPSA Installer [![Build Status](https://travis-ci.org/flack/openpsa-installer.png?branch=master)](https://travis-ci.org/flack/openpsa-installer)
=================

Custom [Composer](http://getcomposer.org) installer for [OpenPSA](http://openpsa2.org)/[MidCOM](http://midgard-project.org/midcom/) packages. 

Due to the logic of Composer, this basically consists of two parts, 
a custom installer, that is to say, an implementation of ``Composer\Installer\InstallerInterface`` which is used for 
packages installed in the ``vendor`` directory, and static functions that are run from script hooks in the root package.

What the installer will to is link all the schema files to the central Midgard 2 schema dir, and all the static 
directories (both from themes and from components' static folders) to ``midcom-static``, so that they are accessible via 
the web server.


Usage
-----
To use the installer in a libary, simply set the ``type`` key to ``midcom-package`` in ``composer.json``. You should 
also add ``openpsa/installer`` to your ``require``s.

To use the installer in a root package, add ``openpsa/installer`` to your ``require``s in ``composer.json``. 
Additionally, add the following hooks:

```json
    "scripts": {
        "post-install-cmd": [
            "openpsa\\installer\\installer::prepare_database",
            "openpsa\\installer\\installer::setup_root_package"
        ],
        "post-update-cmd": [
            "openpsa\\installer\\installer::setup_root_package"
        ]
    },
```

The ``prepare_database`` call is optional, you can remove it if you set up your Midgard database by other means.
