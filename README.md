# Install

Provide installation capability to a repository.

**Table of Contents**
* [Before start](#first)
    * [Folder structure](#folder-structure)
* [Quickstart example](#quickstart-example)
    * [Individual](#individual)
    * [All apps](#all-apps)
* [Executable program](#executable-program) 
* [Post install script](#post-install-script)
* [Use pid file](#use-pid-file)
* [Select shell to use](#select-shell-to-use)
* [Restart on failure](#restart-on-failure)
* [Kill child process](#kill-child-process)
* [Require app](#require-app)
* [Install](#install)
* [License](#license)

## Before start
Before you begin, consider reviewing your code and understanding that this is a tool for automating the installation of an application made in PHP to run on the CLI or as a service in a Linux environment.  
  
Observe that if service was instaled this component will update .service file and perform a daemon-reload, and if is active (runing) before install starts, service will be autoatically started after instalation is finished.  

Note. Not supported on Windows.

### Folder structure
Prepare the folder structure as shown below:
```shell
+-- installation
|   +-- install.php
|   +-- install-instructions.json
+-- pbin
|   +-- my_program_executable
+-- src
|   +-- YourClass.php
+-- post-install
|   +-- myScript.sh
+-- composer.json
```

Note. If you are unsure how to create your 'my_program_executable' go to [Executable program](#executable-program).

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
            "bin" : "my_program_executable",
            "control-by-pid" : true,
            "restart-on-abort" : true
        }
    ],
    "require" : [
        "program1",
        "program2"
    ],
    "post-installation" : "/../post-install/myScript.sh"
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

This will make the contents of the "pbin" folder become executable, as well as create a service on the system with the name "My1stProgram" that will execute the file "my_program_executable".  
At the end will call script myScript.sh (under /post-install), to do some adjusts that you need, like call other aplication, change some file attribute or remove. [Learn more](#post-install-script)  
An "require" is a array that tell which apps your app depends.  
Note that a symlink is created in "/usr/sbin", that way you can run the application by typing in the terminal:
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

Is possible to skip some app installation (partially or entirely).  
To do this, you need to pass an array with app name in key, as follows: 
```php
/**
 * Installation will be made in basic mode, no services or post scripts 
 *  are done in this mode
*/
\BrunoNatali\Install\Factory::installAll( null, array(
    'appToNotInstall' => true  // Don`t metter the value
) );

/**
 * Installation will be entirely skipped
*/
\BrunoNatali\Install\Factory::installAll( null, array(
    'appToNotInstall' => 'force'  // Set value to 'force'
) );
```

Note. The need to run as root, as it will interact with systemd and create / update processes within the system.

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

## Post install script
You can create a bash script to run after installation. For now, this script could do anything.  
To help localization, an environment variable 'INSTALL_DIR' has been added.
```shell
#!/bin/bash

echo "Installing from: $INSTALL_DIR";

$ Installing from: /opt/myapp/vendor/vendor-name/rep-name/installation
```

## Use pid file
Could be configured to use a pid file to control service.  
For this, place an "control-by-pid" in service config:
```json
"service" : [
        {
            "name" : "MyApp",
            "bin" : "my_app_executable",
            "control-by-pid" : true
        }
    ]
```
An pid file is created in \var\run\MyApp.pid when start and removed when stops.

## Select shell to use
To help service load / run using all configs, linux shell is called on execution and "sh" is used for default.  
Just one config is allowed, to change this, place an "shell" in service config with "bash":
```json
"service" : [
        {
            "name" : "MyApp",
            "bin" : "my_app_executable",
            "shell" : "bash"
        }
    ]
```

## Restart on failure
Systemd could restart service if service fails.  
For this, place an "restart-on-abort" in service config:
```json
"service" : [
        {
            "name" : "MyApp",
            "bin" : "my_app_executable",
            "restart-on-abort" : true
        }
    ]
```

## Kill child process
Seting "kill-child" to false will make systemd to let all child of the main process alive on stop / restart.  
For this, place an "kill-child" in service config:
```json
"service" : [
        {
            "name" : "MyApp",
            "bin" : "my_app_executable",
            "kill-child" : false
        }
    ]
```
Remember to manually kill all remaining process to prevent system mem overflow.

## Require app
With "require" set in your config.json you could set which app your application depends.  
Ex. If you have application called "myCar", you can set "require", like this:  
```json
{
    "require" : [
        "engine",
        "wheelsNtires"
    ]
}
```
With this, Install whill install "engine" and "wheelsNtires" before install "myCar"  
Note 1. This will be handled automatically when called from \BrunoNatali\Install\Factory::[installAll()](#all-apps).  
  
To handle manually, an 'require-installed' must be passed to [install()](#individual)
```php
/**
 * engine.php
*/
use BrunoNatali\Install\Factory;
$myApp = new Factory( ["dir" => __DIR__] );
$myApp->install();
```
```php
/**
 * wheelsNtires.php
*/
use BrunoNatali\Install\Factory;
$myApp = new Factory( ["dir" => __DIR__] );
$myApp->install();
```
```php
/**
 * car.php
*/
use BrunoNatali\Install\Factory;
$myApp = new Factory( ["dir" => __DIR__] );
$myApp->install( array(
    'require-installed' => array(
        "engine" => '', // See note 2
        "wheelsNtires" => true,
        "stuff" => 'ok'
    )
));
```
Note 2. In the above example, don't matther what is the value of require-installed item (*EXCEPT FOR 'force' that is reserved internally), just need to be registered as a key.

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
