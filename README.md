# Install

Provide installation capability to a repository.

**Table of Contents**
* [Before start](#first)
    * [Folder structure](#folderstructure)
* [Quickstart example](#quickstart-example)
    * [Individual](#quickIndividual)
    * [All apps](#quickGlobal)
* [Executable program](#executable-program)
* [Install](#install)
* [License](#license)

## Before start
Before you begin, consider reviewing your code and understanding that this is a tool for automating the installation of an application made in PHP to run on the CLI or as a service in a Linux environment.
Note. Not supported on Windows.

### Folder structure
Prepare the folder structure as shown below:

+-- installation
|   +-- install.php
|   +-- install-instructions.json
+-- pbin
|   +-- my_program_executable
+-- src
|   +-- YourClass.php
+-- composer.json

Note. If you are unsure how to create your 'my_program_executable' go to `Executable program`.

## Quickstart example
### Individual
A common use is the individual installation of the application, for that, consider having the folder structure as shown in `Folder structure`.

- Let's create a simple configuration file, "installation / install-instructions.json": 

```json
{
    "sys-bin-files" : [
        "my_program_executable"
    ],
    "service" : [
        {
            "name" : "My1stProgram",
            "bin" : "my_program_executable"
        }
    ]
}
```
- Let's add a script for auto installation "installation / install.php":

```php
use BrunoNatali\Install\Factory;

$myApp = new Factory( ["dir" => __DIR__] );

$myApp->install();
```
- Finally perform the installation:
```shell
$ php vendor/myname/appname/installation/install.php
```

This will make the contents of the "pbin" folder become executable, as well as create a service on the system with the name "My1stProgram" that will execute the file "my_program_executable". Note that a symlink is created in "/ usr / sbin", that way you can run the application by typing in the terminal:
```shell
$ my_program_executable
```

### All apps
In this example, it is assumed that the folder structure shown in `Folder structure` and at least the configuration file "install-instructions.json" is created within all the applications you intend to install.

Within the Factory class there is a static function to make the installation of several applications very easy:
```php
\BrunoNatali\Install\Factory::installAll()
```

The simplest and most direct way to perform the installation of all applications is to run the following command on the terminal:
```shell
$ sudo php -r "require 'vendor/autoload.php'; \BrunoNatali\Install\Factory::installAll();"
```

Note. Note the need to run as root, as it will interact with systemd and create / update processes within the system.

## Executable program
An executable program considered for this tool basically contains:
```php
#!/usr/bin/php

<?php

require __DIR__ . '/../../../autoload.php';

use VendorName\AppName\Service;

$myService = new Service();

$myService->start();
```

## Install

The recommended way to install this library is [through Composer](https://getcomposer.org).
[New to Composer?](https://getcomposer.org/doc/00-intro.md)

This project follows [SemVer](https://semver.org/).
This will install the latest supported version:

```bash
$ composer require brunonatali/install:^1.0
```

This project aims to run on Linux and thus does not require any PHP
extensions, but actually not tested in all environments. If you find a bug, please report.


## License

MIT, see [LICENSE file](LICENSE).
