OpenPSA Installer [![Build Status](https://travis-ci.org/flack/openpsa-installer.png?branch=master)](https://travis-ci.org/flack/openpsa-installer)
=================

Installation and setup tools for [OpenPSA](http://openpsa2.org)/[MidCOM](http://midgard-project.org/midcom/) packages and projects.

## Composer Support

Due to the logic of [Composer](http://getcomposer.org), this basically consists of two parts,
a custom installer, that is to say, an implementation of ``Composer\Installer\InstallerInterface`` which is used for
packages installed in the ``vendor`` directory, and static functions that are run from script hooks in the root package.

What the installer will do is link all the schema files to the central Midgard 2 schema dir, and all the static
directories (both from themes and from components' static folders) to ``midcom-static``, so that they are accessible via
the web server.


### Usage

To use the installer in a libary or component, simply set the ``type`` key to ``midcom-package`` in ``composer.json``. You should
also add ``openpsa/installer`` to your ``require``s.

To use the installer in a root package, add ``openpsa/installer`` to your ``require``s in ``composer.json``.
Additionally, add the following hooks:

```json
    "scripts": {
        "post-install-cmd": [
            "openpsa\\installer\\installer::setup_root_package"
        ],
        "post-update-cmd": [
            "openpsa\\installer\\installer::setup_root_package"
        ]
    },
```

## Database setup

The installer package contains a CLI utility to set up new databases. From your project's root directory, you can run it like this:

```sh
./vendor/bin/openpsa-installer mgd2:setup
```

You can pass the location of an existing Midgard2 configuration file as an argument to the script, or you can optionally specify the DB type you want to create. Run

```sh
./vendor/bin/openpsa-installer help mgd2:setup
```

to see all available options.
