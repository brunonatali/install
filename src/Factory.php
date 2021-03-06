<?php declare(strict_types=1);

namespace BrunoNatali\Install;

use BrunoNatali\Tools\OutSystem;

class Factory implements InstallInterface
{
    private $dir;
    private $pbin;
    private $instructions;

    protected $appName;

    Protected $outSystem;

    function __construct($config = [])
    {

        $config += [
            "dir" => null,
            "pbin" => null,
            "instructions" => null,
            "outSystemEnabled" => true
        ];

        $this->dir = $config['dir'];

        if ($this->dir !== null)
            $this->appName = $this->getAppName();
        else 
            $this->appName = null;

        if ($config['pbin'] !== null) 
            $this->setPBin($config['pbin']);
        else if ($this->dir !== null)
            $this->setPBin(); // Apply default
        else     
            $this->pbin = null;

        if ($config['instructions'] !== null) 
            $this->setInstructions($config['instructions']);
        else if ($this->dir !== null)
            $this->setInstructions(); // Apply default
        else     
            $this->instructions = null;

        $config = OutSystem::helpHandleAppName( 
            $config,
            [
                "outSystemName" => 'Install'
            ]
        );

        $this->outSystem = new OutSystem($config);
    }

    public function setDir(string $dir)
    {
        $this->dir = $dir;
    }

    public function setPBin(string $pbin = null)
    {
        if ($pbin !== null)
            $this->pbin = $pbin;
        else if ($this->dir !== null) 
            $this->pbin = $this->dir . '/../pbin'; // Default location
        else
            throw new \Exception("Provide bin folder or setDir() first", self::INSTALL_WRONG_PBIN);

        if (!file_exists($this->pbin)) {
            throw new \Exception("'pbin' folder does not exist on: " . $this->pbin, self::INSTALL_PBIN_ERROR);
            $this->pbin = null;
        }
    }

    public function setInstructions($instructions = false)
    {
        if (\is_array($instructions)) {
            $this->instructions = $instructions;
        } else if (\is_string($instructions)) {
            if (!\file_exists($instructions))
                throw new \Exception("Instructions file not found in: " . $instructions, self::INSTALL_INS_ERROR);
                 
            $fileContent = \json_decode(\file_get_contents($instructions), true);
            if (!\is_array($fileContent))
                throw new \Exception("Instructions file in wrong format", self::INSTALL_INS_WRONG_FORMAT);

            $this->instructions = $fileContent;
        } else {
            if ($this->dir !== null) 
                $this->setInstructions($this->dir . '/install-instructions.json'); // default location
            else
                throw new \Exception("Instructions must be array or .json file path", self::INSTALL_WRONG_INS);
        }
    }

    public function getAppName(): string
    {
        if ($this->dir === null)
            throw new \Exception("App name could not be determined before set dir", self::INSTALL_DIR_ERROR);

        \chdir($this->dir . '/..'); // Change to app root dir
        return \basename(\getcwd()); // Get APP dir name
    }

    public function install(array $baseInstructions = [])
    {
        $result = 'Nothing to do, check your configuration.';

        if ($this->appName === null)
            $this->appName = $this->getAppName();

        /**
         * Check required apps before start installation
        */
        if (isset($this->instructions['require'])) {
            if (!is_array($this->instructions['require']))
                throw new \Exception("Require must be array", self::INSTALL_INS_REQUIRE_ERROR);

            foreach ($this->instructions['require'] as $require) {
                $require = \trim($require);

                if (!isset($baseInstructions['require-installed']) ||
                    !isset($baseInstructions['require-installed'][$require])) {
                        $this->outSystem->stdout(
                            'Aborting ' . $this->appName . " instalation, required $require not installed.", 
                            OutSystem::LEVEL_NOTICE
                        );

                        return $require;
                    }
            }
                
        }
        
        if (isset($baseInstructions['require-installed'][ $this->appName ]) && 
            $baseInstructions['require-installed'][ $this->appName ] === 'basic') {
            $this->outSystem->stdout('Starting ' . $this->appName . ' BASIC instalation', OutSystem::LEVEL_NOTICE);
            $basicInstall = true;
        } else {
            $this->outSystem->stdout('Starting ' . $this->appName . ' instalation', OutSystem::LEVEL_NOTICE);
            $basicInstall = false;
        }

        
        /**
         * Make all files in pbin folder executable 
        */
        if ($this->pbin !== null) {
            $this->outSystem->stdout('Make binaries executable:', OutSystem::LEVEL_NOTICE);

            $folderContent = \array_diff(\scandir($this->pbin), ['..', '.']);

            foreach ($folderContent as $bin) {
                $result = 'Done.';

                $this->outSystem->stdout("\t- $bin", OutSystem::LEVEL_NOTICE);
                \chmod($this->pbin . '/' . $bin, 0755);
            }
        }

        /**
         * Install binaries to system sbin folder
        */
        if (isset($this->instructions['sys-bin-files'])) {
            if (!\is_array($this->instructions['sys-bin-files']))
                throw new \Exception("'sys-bin-files' must be array", self::INSTALL_SYS_BIN_WRONG_FORMAT);

            $this->outSystem->stdout('Installing binaries:', OutSystem::LEVEL_NOTICE);

            foreach ($this->instructions['sys-bin-files'] as $bin) {
                $symLink = '/usr/sbin/' . basename($bin, '.php');
        
                if (\file_exists($symLink))
                    \unlink($symLink);
        
                $out = [];
                $result = $this->run("ln -s " . $this->pbin . "/$bin $symLink", $out);
        
                if ($result !== 0)
                    throw new \Exception("Error while installing '$bin': " . (\is_array($out) ? \implode(PHP_EOL, $out) : $out), self::INSTALL_SYMLINK_ERROR);
            
                $this->outSystem->stdout("\t- $bin", OutSystem::LEVEL_NOTICE);
                $result = 'Done.';
            }
        }
        
        if ($basicInstall) { // End script due to a basic installation
            $this->outSystem->stdout($result, OutSystem::LEVEL_NOTICE);
            return true;
        }

        /**
         * Crate install & configure services
        */
        if (isset($this->instructions['service'])) {
            if (!\is_array($this->instructions['service']))
                throw new \Exception("'service' must be array", self::INSTALL_SERVICE_WRONG_FORMAT);

            $this->outSystem->stdout('Installing and configuring services:', OutSystem::LEVEL_NOTICE);

            foreach ($this->instructions['service'] as $key => $service) {

                if (!isset($service['name']) || !isset($service['bin'])) 
                    throw new \Exception("Config error in service index $key", self::INSTALL_SERVICE_CONFIG_ERROR);

                $serviceName = $service['name'] . '.service';
                $this->outSystem->stdout("\t- $serviceName", OutSystem::LEVEL_NOTICE);

                $out = [];
                $result = 0;
                $serviceExists = false;
                $serviceIsRunning = false;

                /**
                 * Remove old service before install new
                 * */ 

                if ($this->run("systemctl is-active --quiet $serviceName", $out) === 0) {
                    $this->outSystem->stdout("\t  Stopping service before continue: ", false, OutSystem::LEVEL_NOTICE);
                    if ($this->run('systemctl stop ' . $serviceName, $out) !== 0)
                        $this->outSystem->stdout("[WARNING] Service $serviceName could not be stoped", OutSystem::LEVEL_NOTICE);
                    else
                        $this->outSystem->stdout("OK", OutSystem::LEVEL_NOTICE);
                        
                    $serviceIsRunning = true;
                    $serviceExists = true;
                }

                /**
                 * Check if service is at least registered
                 * 4 mean "Unit service-name.service could not be found."
                */
                if (!$serviceExists && $this->run('systemctl status ' . $serviceName, $out) !== 4) 
                    $serviceExists = true;

                /******* REMOVING ---> check needance & permanent remove on next version
                $this->run('systemctl disable ' . $serviceName, $out);
                $this->run('rm /etc/systemd/system/' . $serviceName, $out);
                $this->run('systemctl daemon-reload', $out);
                $this->run('systemctl reset-failed', $out);
                *****/

                $this->run('uname -r', $out);
                $linuxVersion = \floatval((string) implode("", $out));

                if ($linuxVersion >= 5) 
                    $runPath = '/run/';
                else 
                    $runPath = '/var/run/';

                $content = '[Unit]' . PHP_EOL;
                
                if (isset($service['description']))
                    $content .= 'Description=' . $service['description'] . PHP_EOL;
                else
                    $content .= "Description=Automation service for $serviceName (" . 
                        $service['name'] . ") created using BrunoNatali/Install PHP lib" . PHP_EOL;
                    
                if (isset($service['require-service']))
                    $content .= 'Requires=' . $service['require-service'] . PHP_EOL; // mysql.service
            
                if (isset($service['exec-only-after']))
                    $content .= 'After=' . $service['exec-only-after'] . PHP_EOL; // network.target

                $content .= 'Documentation=https://github.com/brunonatali/install' . PHP_EOL;

                $content .= PHP_EOL . '[Install]' . PHP_EOL;
                $content .= 'WantedBy=multi-user.target' . PHP_EOL;

                $content .= PHP_EOL . '[Service]' . PHP_EOL;
                
                $execPath = \realpath($this->pbin);
                if (!$execPath)
                    $execPath = $this->pbin;

                $useShell = (isset($service['shell']) && \trim($service['shell']) === 'bash' ?
                    'bash' : 'sh');

                if (\file_exists('/bin/' . $useShell))
                    $useShell = "/bin/$useShell";
                else if (\file_exists('/sbin/' . $useShell))
                    $useShell = "/sbin/$useShell";
                else if (\file_exists('/usr/bin/' . $useShell))
                    $useShell = "/usr/bin/$useShell";
                else if (\file_exists('/usr/sbin/' . $useShell))
                    $useShell = "/usr/sbin/$useShell";
                else 
                    throw new \Exception(
                        "Could not find configured shell ($useShell) in your system", 
                        self::INSTALL_SERVICE_CONFIG_ERROR
                    );
                    
                $content .= "ExecStart=$useShell -c '$execPath/" . \trim($service['bin']) . 
                    (isset($service['control-by-pid']) && $service['control-by-pid']  ? 
                        " & echo $! > $runPath" . $service['name'] . '.pid\'' : '\'') . PHP_EOL;
                
                // Do not kill child process on stop / restart service
                if (isset($service['kill-child']) && $service['kill-child'] === false)
                    $content .= 'KillMode=process' . PHP_EOL;

                if (isset($service['control-by-pid']) && $service['control-by-pid'])
                    $content .= "PIDFile=$runPath" . $service['name'] . '.pid' . PHP_EOL;

                $content .= 'KillSignal=SIGTERM' . PHP_EOL;
                $content .= 'SendSIGKILL=no' . PHP_EOL;

                if (isset($service['restart-on-abort']) && $service['restart-on-abort']) {
                    $content .= 'Restart=on-abort' . PHP_EOL;
                    $content .= 'RestartSec=5s' . PHP_EOL;
                }

                $serviceFile = '/etc/systemd/system/' . $serviceName;

                if (\file_exists($serviceFile))
                    @\unlink($serviceFile);

                if (!\file_put_contents($serviceFile, $content) ||
                    !\chmod($serviceFile, 0644) ||
                    $this->run(
                        ($serviceExists ? 'systemctl daemon-reload' : 'systemctl enable ' . $serviceName), 
                        $out
                    ) !== 0) 
                        throw new \Exception("Error while creating service '$serviceName'", 1);

                if ($serviceIsRunning) {
                    $this->outSystem->stdout("\t  Automatically start service: ", false, OutSystem::LEVEL_NOTICE);
                    if ($this->run('systemctl start ' . $serviceName, $out) !== 0)
                        $this->outSystem->stdout('Fail', OutSystem::LEVEL_NOTICE);
                    else
                        $this->outSystem->stdout('OK', OutSystem::LEVEL_NOTICE);
                }

                $result = 'Done.';
            }
        }

        /**
         * Run an script after installation
        */
        if (isset($this->instructions['post-installation'])) {
            if (!\is_string($this->instructions['post-installation']) ||
                !\file_exists($this->dir . $this->instructions['post-installation']))
                throw new \Exception('Post installation file not exist', self::INSTALL_POST_INST_FILE_ERROR);
        
            $this->outSystem->stdout('Running post script: ' . $this->instructions['post-installation'], OutSystem::LEVEL_NOTICE);

            \chmod($this->dir . $this->instructions['post-installation'], 0755); // Make script executable
            \passthru('export INSTALL_DIR=' . $this->dir . ' && ' . $this->dir . $this->instructions['post-installation']); 
        }

        $this->outSystem->stdout($result, OutSystem::LEVEL_NOTICE);

        return true;
    }

    public static function installAll(string $dir = null, array $remove = []): bool
    {
        if ($dir === null)
            $dir = __DIR__ . '/../../../'; // Install/brunonatali/vendor

        $stdoutConfig = [
            'outSystemName' => 'InstallALL'
        ];
        OutSystem::dstdout("Searching installable apps in " . \realpath($dir), $stdoutConfig);

        $installedApps = [
            'require-installed' => []
        ];
        
        /**
         * Anonymous function to do a recursive search in vendor root folder
        */
        $getDirContent = function ($dir, $found = false) use (
            &$getDirContent, &$appsToInstall, &$installedApps, $remove, $stdoutConfig
            ) {
            foreach (\array_diff(\scandir($dir), ['..', '.']) as $item) {
                $path = realpath("$dir/$item");

                if ($found) {

                    if ($item === 'install-instructions.json')
                        return true;

                } else {
                    if (is_dir($path)) {
                        if ($item === 'installation') {
                            
                            $rmAppName = \basename($dir);
                            if (isset($remove[ $rmAppName ])) {
                                if ($remove[ $rmAppName ] === 'force') {
                                    // Add as app installed to not block apps that need this
                                    $installedApps['require-installed'][ $rmAppName ] = 'not-install';
                                    $msg = 'Aborting ' . $rmAppName . ' intallation. Registered to not install.';
                                } else {
                                    // App will be installed without critical content, like service
                                    $installedApps['require-installed'][ $rmAppName ] = 'basic';
                                    $msg = 'Basic installation registered to ' . $rmAppName ;
                                }

                                OutSystem::dstdout(
                                    $msg, 
                                    $stdoutConfig
                                );
                            }

                            if ($getDirContent($path, true) === true);
                                $appsToInstall[] = $path;

                        } else {
                            $getDirContent($path);
                        }
                    } 
                }
            }
        };

        $appsToInstall = [];
        $getDirContent($dir); // Build list

        if (($count = count($appsToInstall)) === 0) {
            OutSystem::dstdout("No one app to install", $stdoutConfig);
            return false;
        }

        OutSystem::dstdout("Found $count apps", $stdoutConfig);

        $installAll = function (&$appsToInstall, &$installedApps) use ($stdoutConfig)
        {
            foreach ($appsToInstall as $i => $app) {
                $myApp = new Factory([
                    'dir' => $app
                ] + $stdoutConfig);

                $result = $myApp->install($installedApps);
                if ($result === true) {
                    $installedApps['require-installed'][ $myApp->appName ] = $myApp->appName;
                    unset($appsToInstall[$i]);
                }
            }

            return count($appsToInstall);
        };

        $last = count($appsToInstall);
        
        while (($rest = $installAll($appsToInstall, $installedApps))) {
            if ($last !== $rest) {
                OutSystem::dstdout("Retry installation of: $rest", $stdoutConfig);
                $last = $rest;
            } else {
                OutSystem::dstdout("Critical error, could not install: $rest", $stdoutConfig);
                break;
            }
        }

        return true;
    }

    private function run(string $cmd, array &$out): int
    {
        $out = [];
        $result = 0;
        @\exec($cmd, $out, $result);
    
        return $result;
    }
}