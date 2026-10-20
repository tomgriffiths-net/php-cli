<?php
/**
 * Extension management.
 */
class extensions{
    //Windows
    /**
     * WINDOWS ONLY - Gets an extensions zip url from pecl.php.net.
     *
     * @param string $extension The name of the extension on pecl.
     * @return string|null The url on success or null on failure.
     */
    public static function windowsPeclUrl(string $extension):?string{
        if(!preg_match('/^[a-z0-9_-]{1,64}$/', $extension)){
            mklog0(3, "Invalid extension name $extension");
            return false;
        }

        if(!extension_loaded('openssl')){
            mklog0(3, "OpenSSL extension not enabled, this is needed to access pecl.php.net");
            return false;
        }

        $version = trim(@file_get_contents("https://pecl.php.net/rest/r/$extension/stable.txt"));
        if(!$version){
            mklog0(3, "Failed to get latest version for $extension");
            return null;
        }

        $build    = self::buildInfo();
        $phpVer   = self::phpVersion();
        $arch     = PHP_INT_SIZE === 8 ? 'x64' : 'x86';
        $filename = "php_$extension-$version-$phpVer-" . $build['ts'] . "-" . $build['vs'] . "-$arch.zip";
        $url      = "https://windows.php.net/downloads/pecl/releases/$extension/$version/$filename";

        // HEAD request to confirm the file actually exists
        $headers = @get_headers($url);
        if(!$headers || !str_contains($headers[0], '200')){
            mklog0(3, "Could not get compatible version for $extension");
            return null;
        }

        return $url;
    }
    /**
     * WINDOWS ONLY - Downloads and unzips a pecl.php.net extension.
     *
     * @param string $extension the pecl extension name.
     * @param string|null $dest_dir The directory to unzip the extension dll to, if null the current php.ini extension_dir is used.
     * @return boolean Weather the extension was downloaded and unzipped successfully.
     */
    public static function windowsInstallPeclExtension(string $extension, ?string $dest_dir=null):bool{
        $url = self::windowsPeclUrl($extension);
        if(!$url){
            mklog0(2, "No Windows build found for $extension matching your PHP config");
            return false;
        }

        $dest_dir = $dest_dir ?? ini_get('extension_dir');
        if(!$dest_dir){
            mklog0(3, 'Could not determine extension directory');
            return false;
        }

        $filename = basename($url);
        $tmp_zip  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

        // download zip to temp
        mklog0(1, "Downloading $filename");
        $zip_data = file_get_contents($url);
        if(!$zip_data){
            mklog0(3, "Failed to download $filename");
            return false;
        }
        if(!file_put_contents($tmp_zip, $zip_data)){
            mklog0(3, "Failed to save $filename");
            return false;
        }

        // extract just the dll
        $zip = new ZipArchive();
        if($zip->open($tmp_zip) !== true){
            mklog0(3, "Failed to open zip $tmp_zip");
            unlink($tmp_zip);
            return false;
        }

        $dll_name = "php_$extension.dll";
        $dest     = rtrim($dest_dir, '\\/') . "/" . $dll_name;

        if($zip->locateName($dll_name) === false){
            mklog0(3, "Could not find $dll_name in downloaded zip");
            $zip->close();
            unlink($tmp_zip);
            return false;
        }

        if(file_put_contents($dest, $zip->getFromName($dll_name))){
            mklog0(3, "Failed to save dll from downloaded zip to extension dir");
            $zip->close();
            unlink($tmp_zip);
            return false;
        }

        $zip->close();
        unlink($tmp_zip);

        mklog0(1, "Installed $dll_name to $dest");
        return true;
    }
    //Linux
    /**
     * UBUNTU/LINUX ONLY - Ensures the ondrej/php apt repository is enabled.
     *
     * @return boolean Weather the repository is enabled.
     */
    public static function linuxEnsurePhpRepo():bool{
        // Check if the ondrej/php repo is already present
        exec("grep -rq 'ondrej/php' /etc/apt/sources.list /etc/apt/sources.list.d/ 2>/dev/null", $_, $grepExit);
        if($grepExit === 0){
            //grep found the apt repo exists
            return true;
        }

        // Ensure add-apt-repository is available
        exec("command -v add-apt-repository 2>/dev/null", $_, $cmdExit);
        if($cmdExit !== 0){
            mklog0(1, "add-apt-repository command not found. Installing software-properties-common");

            if(!linuxcmd::updateApt()){
                mklog0(2, "Failed to run apt update, package versions may be out of date, continuing");
            }

            if(linuxcmd::sudo("apt install software-properties-common -y -qq") !== 0) {
                mklog0(3, "Failed to install software-properties-common");
                return false;
            }
        }

        // Add the ondrej/php PPA
        mklog0(1, "Adding ondrej/php apt repository");
        if(linuxcmd::sudo("LC_ALL=C.UTF-8 add-apt-repository ppa:ondrej/php -y") !== 0){
            mklog0(3, "Failed to add ondrej/php apt repository");
            return false;
        }

        // Update apt after adding the new repo
        if(!linuxcmd::updateApt(true)){
            mklog0(3, "Failed to update apt after adding ondrej/php repository");
            return false;
        }

        return true;
    }

    //Both
    /**
     * Checks weather the extension is loaded, if the extension is not loaded it will try to enable it so it is available after a restart of PHP-CLI.
     *
     * @param string $extension The extension name.
     * @return boolean Weather the extension is enabled.
     */
    public static function ensure(string $extension):bool{
        if(extension_loaded($extension)){
            return true;
        }

        self::setEnabled($extension, true);
        return false;
    }
    /**
     * Returns extension info, contains a list of builtin extensions and bundled extensions and install names for extensions where their install name is different to the extension name reported.
     * Access the lists like this: $extensionInfo['builtin'][PHP_OS_FAMILY] or $extensionInfo['bundled'][PHP_OS_FAMILY] or $extensionInfo['installnames'][PHP_OS_FAMILY].
     * The installnames is not a list, it is an array where the keys are the wanted extension name and the values are the extension to install that gets the original name.
     * Only contains info for Windows (8.5 ts zip) and Linux (Ubuntu 24.04.4 LTS on php8.5 from ondrej/php), last updated on 2026-05-27.
     *
     * @return array The array of information about extensions.
     */
    public static function extensionInfo():array{
        return [
            'builtin' => [
                'Linux' => [
                    'core','date','filter','hash','json','lexbor','libxml','openssl','pcntl','pcre','random','reflextion','session','sodium','spl','standard','uri','Zend OPcache','zlib'
                ],
                'Windows' => [
                    'bcmath','calendar','core','ctype','date','dom','filter','hash','iconv','json','lexbor','libxml','mysqlnd','pcre','pdo','phar','random','readline','reflection','session','simplexml','spl','standard','tokenizer','uri','xml','xmlreader','xmlwriter','Zend OPcache','zlib'
                ]
            ],
            'bundled' => [
                'Linux' => [
                    'calendar','ctype','exif','ffi','fileinfo','ftp','gettext','iconv','pdo','phar','posix','readline','shmop','sockets','sysvmsg','sysvsem','sysvshm','tokenizer'
                ],
                'Windows' => [
                    'bz2','com_dotnet','curl','dba','enchant','exif','ffi','fileinfo','ftp','gd','gettext','gmp','intl','ldap','mbstring','mysqli','odbc','openssl','pdo_firebird','pdo_mysql','pdo_odbc','pdo_pgsql','pdo_sqlite','pgsql','shmop','snmp','soap','sockets','sodium','sqlite3','sysvshm','tidy','xsl','zip'
                ]
            ],
            'installnames' => [
                'Linux' => [
                    'mysqli'     => 'mysql',
                    'pdo_mysql'  => 'mysql',
                    'pdo_odbc'   => 'odbc',
                    'pdo_pgsql'  => 'pgsql',
                    'pdo_sqlite' => 'sqlite3',
                    'pdo_dblib'  => 'sybase',
                    'dom'        => 'xml',
                    'simplexml'  => 'xml',
                    'xmlreader'  => 'xml',
                    'xmlwriter'  => 'xml',
                    'xsl'        => 'xml',
                ],
                'Windows' => []
            ]
        ];
    }
    /**
     * Gets the build info for php, thread safety and vs version.
     *
     * @return array An array with build info, $buildInfo['ts'] will be "nts" or "ts" and $buildInfo['vs'] will be something like "vs17".
     */
    public static function buildInfo():array{
        ob_start();
        phpinfo(INFO_GENERAL);
        $info = ob_get_clean();

        // "PHP Extension Build => API20250925,NTS,VS17"
        if(preg_match('/PHP Extension Build\s*=>\s*API\d+,(\w+),(VS\d+)/i', $info, $m)){
            return [
                'ts'  => strtolower($m[1]),  // "NTS" or "TS"
                'vs'  => strtolower($m[2]),  // "VS17" -> "vs17"
            ];
        }

        mklog0(1, "Using fallback build info");

        // fallback
        return [
            'ts' => ZEND_THREAD_SAFE ? 'ts' : 'nts',
            'vs' => 'vs17',
        ];
    }
    /**
     * Checks weather some extensions are loaded.
     *
     * @param array $extensions A list of extension names.
     * @return boolean Weather all the extensions are loaded.
     */
    public static function areLoaded(array $extensions):bool{
        $areLoaded = true;

        foreach($extensions as $extension){
            if(!extension_loaded($extension)){
                $areLoaded = false;
                break;
            }
        }

        return $areLoaded;
    }
    /**
     * Returns the php major and minor version with a dot seperating them.
     *
     * @return string returns PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION.
     */
    public static function phpVersion():string{
        return PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    }
    /**
     * Enables or disables an extension, uses phpenmod and phpdismod on ubuntu or edits the php.ini file on windows.
     *
     * @param string $extension The extension name.
     * @param boolean $enabled Weather to enable or disable the extension.
     * @param boolean $allowDownload Weather to allow downloading if enableing and not already installed.
     * @return boolean Weather the operation was successful.
     */
    public static function setEnabled(string $extension, bool $enabled=true, bool $allowDownload=true):bool{
        if(!preg_match('/^[a-z0-9_-]{1,64}$/', $extension)){
            mklog0(2, "Invalid extension name $extension");
            return false;
        }

        if(extension_loaded($extension) && $enabled){
            mklog0(1, "Extension $extension is already enabled");
            return true;
        }

        if(self::installed($extension)){
            mklog0(1, "Enabling extension " . $extension . (function_exists('mklog') ? ", you will need to restart PHP-CLI for the extension to become loaded" : ""));
            if(PHP_OS_FAMILY === 'Linux'){
                $exit = linuxcmd::sudo(($enabled ? "phpenmod " : "phpdismod ") . $extension);
                if($exit !== 0){
                    mklog0(3, ($enabled ? "phpenmod " : "phpdismod ") . " command reported error code " . $exit);
                    return false;
                }
                return true;
            }
            else{
                return inimgmt::windowsEnableExtension($extension, $enabled);
            }
        }

        if(!$enabled){//extension that is not installed to be disabled
            return true;
        }

        if(!$allowDownload){
            mklog0(3, "Extension $extension is not installed and allowDownload is false");
            return false;
        }

        mklog0(1, "Installing extension " . $extension);
        if(!self::install($extension)){
            mklog0(3, "Failed to install " . $extension);
            return false;
        }

        if(!self::setEnabled($extension, true, false)){
            mklog0(3, "Failed to enable " . $extension . " after install");
            return false;
        }

        return true;
    }
    /**
     * Installs an extension using platform specific methods.
     *
     * @param string $extension The name of the extension.
     * @return boolean Weather the extension is now installed.
     */
    public static function install(string $extension):bool{
        if(!preg_match('/^[a-z0-9_-]{1,64}$/', $extension)){
            mklog0(2, "Invalid extension name $extension");
            return false;
        }

        $extensionName = $extension;
        $nicknames = self::extensionInfo()['installnames'][PHP_OS_FAMILY];
        if(isset($nicknames[$extension])){
            $extensionName = $nicknames[$extension];
        }

        if(PHP_OS_FAMILY === 'Linux'){
            if(!self::linuxEnsurePhpRepo()){
                mklog0(3, "Failed to ensure the ondrej/php apt repository was enabled");
                return false;
            }

            $command = "apt install php" . self::phpVersion() . "-" . $extensionName . " -y";
            if(linuxcmd::sudo($command) !== 0){
                mklog0(3, "Failed to run " . $command);
                return false;
            }
        }
        else{
            if(!self::windowsInstallPeclExtension($extensionName)){
                mklog0(3, "Failed to download pecl extension " . $extensionName);
                return false;
            }
        }

        return true;
    }
    /**
     * Checks weather an extension is in stalled.
     *
     * @param string $extension The name of the extension.
     * @return boolean Weather it is installed.
     */
    public static function installed(string $extension):bool{
        if(!preg_match('/^[a-z0-9_-]{1,64}$/', $extension)){
            mklog0(2, "Invalid extension name $extension");
            return false;
        }

        $extensionDir = ini_get('extension_dir');
        if(PHP_OS_FAMILY === 'Linux'){
            $extensionFile = $extension . ".so";
        }
        else{
            $extensionFile = "php_" . $extension . ".dll";
        }

        return file_exists($extensionDir . "/" . $extensionFile);
    }
}
/**
 * php.ini management.
 */
class inimgmt{
    /**
     * Gets a list of hard coded php.ini settings when in the cli sapi. Last updated on php 8.5.
     *
     * @return array
     */
    public static function cliHardCoded():array{
        return [
            'html_errors',
            'implicit_flush',
            'max_execution_time',
            'memory_limit',
            'register_argc_argv',
            'output_buffering',
            'max_input_time',
        ];
    }
    /**
     * Goes over a list of lines and replaces the first line that starts with a specific string.
     *
     * @param array $lines The list of lines.
     * @param string $starting What the line to replace starts with.
     * @param string $replacement What to replace the line with.
     * @return boolean Weather a line was found and replaced.
     */
    public static function replaceLineBeginingWith(array &$lines, string $starting, string $replacement):bool{
        $count = strlen($starting);
        $somethingHappened = false;
        foreach($lines as $index => $line){
            $line = trim($line);
            if(empty($line)){
                continue;
            }

            if(substr($line,0,$count) === $starting){
                $lines[$index] = $replacement . "\n";
                $somethingHappened = true;
                break;
            }
        }

        return $somethingHappened;
    }
    /**
     * Checks weather some php.ini settings match expected values.
     *
     * @param array $expected The expected values, the keys are ini_get names and the values are compared to ini_get return.
     * @return boolean Weather all the specified settings match.
     */
    public static function doesSettingsMatch(array $expected):bool{
        $allMatch = true;
        $cliHardCoded = inimgmt::cliHardCoded();

        foreach($expected as $settingName => $settingValue){
            if(in_array($settingName, $cliHardCoded)){
                continue;
            }

            $iniSetting = ini_get($settingName);

            if($iniSetting !== $settingValue){
                $allMatch = false;
                break;
            }
        }

        return $allMatch;
    }
    /**
     * Gets the loaded php.ini file, and checks existance.
     *
     * @return string|null The path on success or null on failure.
     */
    public static function iniPath():?string{
        $iniPath = php_ini_loaded_file();
        if(!is_string($iniPath) || !file_exists($iniPath)){
            return null;
        }
        return $iniPath;
    }
    /**
     * Backs up the loaded php.ini.
     *
     * @return boolean Weather the backup was successful.
     */
    public static function backupIni():bool{
        $iniPath = self::iniPath();
        if(!$iniPath){
            return false;
        }

        $backupPath = $iniPath . date('.Y-m-d_H-i-s') . '.bak';

        return copy($iniPath, $backupPath);
    }
    /**
     * Reads the current php.ini using file() and returns the lines in a list.
     *
     * @param boolean $checkWriteable Weather to check if the file is writable.
     * @return array|null The lines on success or null on failure.
     */
    public static function loadIni(bool $checkWriteable=true):?array{
        $path = self::iniPath();
        if(!$path){
            return null;
        }

        if($checkWriteable && !is_writable($path)){
            return null;
        }

        $lines = file($path);
        if(!is_array($lines)){
            return null;
        }

        return $lines;
    }
    /**
     * Sets php.ini settings given a list of lines.
     *
     * @param array $lines The list of lines that represents a php.ini.
     * @param array $settings An array where the keys are setting names and values are setting values, the values will need to be escaped before passing here.
     * @return boolean Weather all the settings could be set.
     */
    public static function setSettingsOnLines(array &$lines, array $settings):bool{
        $success = true;
        foreach($settings as $settingName => $settingValue){
            $replacement = $settingName . " = " . $settingValue;
            if(!self::replaceLineBeginingWith($lines, $settingName, $replacement)){
                if(!self::replaceLineBeginingWith($lines, ';' . $settingName, $replacement)){
                    $success = false;
                }
            }
        }
        return $success;
    }
    /**
     * Should only be used on windows - Edits the loaded php.ini to enable or disable extensions.
     *
     * @param string $extension The extension name.
     * @param boolean $enabled Weather to enable or disable the extension.
     * @param boolean $backupIni Weather to create a backup of the php.ini before editing.
     * @return boolean Weather the edit was successful.
     */
    public static function windowsEnableExtension(string $extension, bool $enabled, bool $backupIni=true):bool{
        if(!preg_match('/^[a-z0-9_-]{1,64}$/', $extension)){
            mklog0(2, "Invalid extension name $extension");
            return false;
        }

        if(extension_loaded($extension) && $enabled){
            mklog0(1, "Extension $extension is already enabled");
            return true;
        }

        $lines = self::loadIni();
        if(!$lines){
            mklog0(2,"Failed to load php.ini");
            return false;
        }

        $extensionLine = "extension=" . $extension;
        $extensionLineIndex = null;
        foreach($lines as $index => $line){
            if(str_contains(str_replace(" ", "", $line), $extensionLine)){
                $extensionLineIndex = $index;
                break;
            }
        }

        if($extensionLineIndex === null){
            $lastExtensionIndex = count($lines)-1;
            foreach($lines as $index => $line){
                if(str_contains($line, "extension=")){
                    $lastExtensionIndex = $index;
                }
            }

            array_splice($lines, $lastExtensionIndex, 0, ["\n"]);//Adds empty line for new extension line
            $extensionLineIndex = $lastExtensionIndex +1;
        }

        $lines[$extensionLineIndex] = ($enabled ? "" : ";") . $extensionLine . "\n";

        return self::saveIni($lines, $backupIni);
    }
    /**
     * Set php.ini settings.
     *
     * @param array $settings An array of settings, the keys are setting names and the values are setting values, the values need to be escaped before passing here.
     * @return boolean Weather the setting changes were successful.
     */
    public static function setIniSettings(array $settings):bool{
        $lines = self::loadIni();
        if(!$lines){
            mklog0(3, "Failed to load php.ini");
            return false;
        }

        if(!self::setSettingsOnLines($lines, $settings)){
            mklog0(3, "Failed to set ini settings");
            return false;
        }

        if(!self::saveIni($lines)){
            mklog0(3, "Failed to save php.ini");
            return false;
        }

        return true;
    }
    /**
     * Saves lines to the current php.ini.
     *
     * @param array $lines The lines to replace the php.ini file with.
     * @param boolean $backup Weather to create a backup of the php.ini before editing.
     * @return boolean Weather the operation was successful.
     */
    public static function saveIni(array $lines, bool $backup=true):bool{
        if($backup){
            if(!self::backupIni()){
                mklog0(3, "Failed to create backup of php.ini before modification");
                return false;
            }
        }

        $path = self::iniPath();
        if(!$path){
            return false;
        }

        return file_put_contents($path, $lines) !== false;
    }
}
/**
 * Finds out if PHP-CLI was started with adminstrative privileges. (Ubuntu + Windows)
 */
class is_admin{
    private static ?bool $state = null;
    
    /**
     * Checks weather the PHP-CLI process is running as an admin. Only checks on the first call, further calls get a cached result.
     *
     * @return boolean Weather the process was run as admin or not.
     */
    public static function check():bool{
        if(!is_bool(self::$state)){
            mklog0(0,"Checking for Administrator privilages");

            if(PHP_OS_FAMILY === 'Linux'){
                self::$state = (posix_geteuid() === 0);
            }
            else{
                exec("net session >nul 2>&1", $_, $resultCode);
                self::$state = ($resultCode === 0);
            }
        }

        return self::$state;
    }
    /**
     * Cleares the check() cache and calls check again then returns its new value.
     *
     * @return boolean Weather the process was run as admin or not.
     */
    public static function refresh():bool{
        self::$state = null;
        return self::check();
    }
}
/**
 * Functions specifically for Ubuntu/Linux.
 */
class linuxcmd{
    /**
     * @var integer The timestamp of the last apt update.
     */
    private static $lastAptUpdate = 0;

    /**
     * Runs a command under sudo.
     *
     * @param string $command The command to be passed to sudo.
     * @return integer The exit code of the command.
     */
    public static function sudo(string $command):int{
        // sudo output bypasses stdout, it goes directly to tty.
        $process = proc_open('sudo ' . $command, [
            0 => STDIN,                          // sudo can read password from terminal
            1 => ['file', '/dev/null', 'w'],     // suppress stdout
            2 => ['file', '/dev/null', 'w'],     // suppress stderr
        ], $pipes);

        return proc_close($process);        // blocks until done
    }
    /**
     * Checks if an apt package is installed.
     *
     * @param string $package The package name.
     * @return boolean Weather the package is installed.
     */
    public static function isAptPackageInstalled(string $package):bool{
        exec('dpkg-query -W ' . escapeshellarg($package), $output, $exitCode);
        return $exitCode === 0;
    }
    /**
     * Runs apt update under sudo, has a 1 hour timeout.
     *
     * @param boolean $ignoreTimeout Weather to ignore the timeout.
     * @return boolean Weather the update was a success or an update was done in the last hour and ignoretimeout is false.
     */
    public static function updateApt(bool $ignoreTimeout=false):bool{
        if(time() - self::$lastAptUpdate > 3600 && !$ignoreTimeout){
            return true;
        }

        mklog0(1, "Running apt update");

        if(self::sudo("apt update") === 0){
            self::$lastAptUpdate = time();
            return true;
        }
        return false;
    }
}
/**
 * Functions for windows specific things.
 */
class windowscmd{
    /**
     * Quote one argument using Windows command-line rules, escape quotes and slashes.
     * @param string $a The argument that will be quoted.
     * @return string The quoted argument.
     */
    public static function processArg(string $a): string {
        if($a !== '' && !preg_match('/[\s"]/', $a)){
            return $a;
        }
        $a = preg_replace('/(\\\\*)"/', '$1$1\\"', $a); // escape quotes (and backslashes before them)
        $a = preg_replace('/(\\\\+)$/', '$1$1', $a);    // double trailing backslashes
        return '"' . $a . '"';
    }

    /**
     * Start $exe elevated (triggers UAC) and waits for the exe to exit.
     * @param string $exe 
     * @param array $args 
     * @return int The exit code of the process or 1223 (ERROR_CANCELLED) if the user clicks "No" on the UAC prompt.
     */
    public static function runElevated(string $exe, array $args = []): int {
        $argStr  = implode(' ', array_map('windowscmd::processArg', $args));

        $script = 'try { $p = Start-Process -FilePath ' . self::psQuote($exe)
            . ($argStr !== '' ? ' -ArgumentList ' . self::psQuote($argStr) : '')
            . ' -Verb RunAs -Wait -PassThru; exit $p.ExitCode } catch { exit 1223 }';

        // -EncodedCommand (UTF-16LE base64) avoids cmd.exe/PowerShell quoting problems entirely
        $encoded = base64_encode(iconv('UTF-8', 'UTF-16LE', $script));
        passthru("powershell -NoProfile -ExecutionPolicy Bypass -EncodedCommand $encoded", $code);
        return $code;
    }

    /**
     * Quotes a string.
     * @param string $s The string to be quoted.
     * @return string The quoted string.
     */
    public static function psQuote(string $s):string{
        return "'" . str_replace("'", "''", $s) . "'";
    }
}
class sysenv{
    /**
     * Returns the full detected environment info.
     * Cached per-request (static var) since none of this changes mid-script.
     */
    public static function info():array{
        static $cache = null;
        if(is_array($cache)){
            return $cache;
        }

        $family = self::detectFamily();

        $data = [
            'family'      => $family,        // windows | linux | darwin | bsd | unknown
            'distro'      => null,           // ubuntu, debian, linuxmint, kali, ... (linux only)
            'distro_like' => [],             // e.g. ['debian'] for mint/kali - fallback matching
            'version'     => null,           // full version: 22.04, 12, 11, 10, 23.1 ...
            'major'       => null,           // major version only: 22, 12, 11, 10, 23 ...
            'build'       => null,           // windows build number, if applicable
            'arch'        => self::detectArch(),
            'id'          => null,           // combined normalized id, e.g. "ubuntu-22"
        ];

        switch ($family) {
            case 'windows':
                $win = self::detectWindows();
                $data['version'] = $win['version'];
                $data['major']   = $win['version']; // already just "10" / "11"
                $data['build']   = $win['build'];
                $data['id']      = 'windows-' . $win['version'];
                break;

            case 'linux':
                $lin = self::detectLinuxDistro();
                $data['distro']      = $lin['id'];
                $data['distro_like'] = $lin['id_like'];
                $data['version']     = $lin['version'];
                $data['major']       = $lin['major'];
                $data['id']          = $lin['id'] . ($lin['major'] ? '-' . $lin['major'] : '');
                break;

            case 'darwin':
                $ver = trim((string) shell_exec('sw_vers -productVersion 2>/dev/null'));
                $ver = $ver !== '' ? $ver : php_uname('r');
                $data['version'] = $ver;
                $data['major']   = self::majorVersion($ver);
                $data['id']      = 'darwin-' . $data['major'];
                break;

            default:
                $data['id'] = 'unknown';
        }

        return $cache = $data;
    }

    /**
     * Gets info on the terminal session php-cli is running in.
     * Platform info (windows/linux/etc.) lives in sysenv::info().
     * Cached per-request (static var) since none of this changes mid-script.
     *
     * @return array An array of booleans with string keys; desktop, headless, remote_desktop, ssh,
     *               modern_terminal, compat_layer, wsl, tty, interactive, daemon_or_cron.
     */
    public static function terminalInfo():array{
        static $cache = null;
        if(is_array($cache)){
            return $cache;
        }

        $family = self::info()['family'];

        // Unified environment accessor
        $env = static fn(string $key): string|false => getenv($key);

        // --- Detect TTY / interactive console ---
        // STDIN/STDOUT are only defined under the CLI SAPI
        $hasTty = false;
        if(defined('STDIN') && defined('STDOUT')){
            if(function_exists('stream_isatty')){
                $hasTty = @stream_isatty(STDIN) || @stream_isatty(STDOUT);
            }
            elseif(function_exists('posix_isatty')){
                $hasTty = @posix_isatty(STDIN) || @posix_isatty(STDOUT);
            }
        }

        // --- Shared checks ---
        $isSSH = !empty($env('SSH_CLIENT')) || !empty($env('SSH_TTY'));

        // --- Defaults ---
        $isDesktop        = false;
        $isRemoteDesktop  = false;
        $isModernTerminal = false;
        $isCompatLayer    = false;
        $isWSL            = false;

        switch ($family) {
            case 'windows':
                // CLIENTNAME is far more reliable than SESSIONNAME
                // Local sessions normally report "Console"
                // RDP sessions report remote client hostname
                $clientName = $env('CLIENTNAME');
                $isRemoteDesktop = !empty($clientName) && strtoupper($clientName) !== 'CONSOLE';

                // If we have a TTY and are not running through SSH,
                // assume an interactive desktop session
                $isDesktop = $hasTty && !$isSSH;

                // Modern terminal detection
                $isModernTerminal =
                    !empty($env('WT_SESSION')) ||      // Windows Terminal
                    !empty($env('WT_PROFILE_ID')) ||
                    !empty($env('ConEmuPID')) ||       // ConEmu
                    !empty($env('TERM_PROGRAM')) ||    // VSCode/etc
                    !empty($env('ANSICON'));

                // Compatibility / subsystem layers
                $isCompatLayer =
                    !empty($env('MSYSTEM')) ||         // MSYS2 / Git Bash
                    !empty($env('CYGWIN'));
                break;

            case 'linux':
                // WSL: WSL_DISTRO_NAME / WSL_INTEROP are set for processes launched through wsl.exe.
                // The interop file can be "WSLInterop" or "WSLInterop-late" depending on WSL version.
                // Under WSL there is no Linux desktop - Windows owns it - so 'desktop' stays false
                // and callers should check 'wsl' to open windows via Windows interop instead.
                $isWSL =
                    !empty($env('WSL_DISTRO_NAME')) ||
                    !empty($env('WSL_INTEROP')) ||
                    file_exists('/proc/sys/fs/binfmt_misc/WSLInterop') ||
                    file_exists('/proc/sys/fs/binfmt_misc/WSLInterop-late');

                $hasDisplay = !empty($env('DISPLAY')) || !empty($env('WAYLAND_DISPLAY'));

                $hasDesktopSession =
                    !empty($env('DESKTOP_SESSION')) ||
                    !empty($env('XDG_CURRENT_DESKTOP')) ||
                    !empty($env('GNOME_DESKTOP_SESSION_ID')) ||
                    !empty($env('KDE_FULL_SESSION'));

                $isDesktop = $hasDisplay && $hasDesktopSession && !$isSSH;

                // Remote desktop detection
                $isRemoteDesktop = !empty($env('XRDP_SESSION')) || !empty($env('X2GO_SESSION'));

                // Modern terminal detection
                $term        = $env('TERM');
                $termProgram = $env('TERM_PROGRAM');

                $isModernTerminal = !empty($env('VTE_VERSION')) || !empty($termProgram) ||
                    in_array(
                        $term,
                        [
                            'xterm-256color',
                            'screen-256color',
                            'tmux-256color',
                            'alacritty',
                            'wezterm',
                        ],
                        true
                    );

                // Wine / WSL / compatibility layers
                $isCompatLayer = $isWSL || !empty($env('WINEPREFIX'));
                break;

            default:
                $isDesktop = $hasTty && !$isSSH;
        }

        return $cache = [
            // Environment type
            'desktop'         => $isDesktop,
            'headless'        => !$isDesktop,
            'remote_desktop'  => $isRemoteDesktop,
            'ssh'             => $isSSH,
            'modern_terminal' => $isModernTerminal,
            'compat_layer'    => $isCompatLayer,
            'wsl'             => $isWSL,

            // TTY / interactivity
            'tty'             => $hasTty,
            'interactive'     => $hasTty && !$isSSH,
            'daemon_or_cron'  => !$hasTty && !$isSSH,
        ];
    }

    /**
     * Check compatibility against a list of allowed patterns.
     * Supports exact ids, distro-only (no version = any version),
     * and "*-like" family fallbacks via distro_like.
     *
     * Examples:
     *   sysenv_matches(['ubuntu-22', 'ubuntu-24'])
     *   sysenv_matches(['ubuntu'])              // any ubuntu version
     *   sysenv_matches(['debian'])              // matches debian AND mint/kali (via id_like)
     *   sysenv_matches(['windows-10', 'windows-11'])
     */
    public static function matches(array $allowed):bool{
        $info = self::info();

        foreach ($allowed as $pattern) {
            // Exact full id match
            if ($pattern === $info['id']) {
                return true;
            }
            // Distro/family-only match (no version specified)
            if ($info['distro'] !== null && $pattern === $info['distro']) {
                return true;
            }
            if ($info['family'] !== null && $pattern === $info['family']) {
                return true;
            }
            // "id_like" fallback, e.g. pattern "debian" matches Mint/Kali
            if (in_array($pattern, $info['distro_like'], true)) {
                return true;
            }
        }

        return false;
    }

    public static function architecture():string{
        return self::info()['arch'];
    }

    /** Is this a 64-bit architecture? */
    public static function is64bit():bool{
        return in_array(self::info()['arch'], ['x86_64', 'amd64', 'arm64', 'aarch64'], true);
    }

    private static function detectFamily():string{
        return match (PHP_OS_FAMILY){
            'Windows' => 'windows',
            'Linux'   => 'linux',
            'Darwin'  => 'darwin',
            'BSD'     => 'bsd',
            default   => 'unknown',
        };
    }

    private static function detectArch():string{
        $m = php_uname('m');
        return $m !== '' ? strtolower($m) : 'unknown';
    }

    /** Extracts the major (leading numeric) component from a version string. */
    private static function majorVersion(?string $version):?string{
        if($version === null || $version === ''){
            return null;
        }
        if(preg_match('/^(\d+)/', $version, $m)){
            return $m[1];
        }
        return $version;
    }

    /**
     * Reads /etc/os-release for machine-readable distro identity.
     * ID = e.g. ubuntu, debian, linuxmint, kali, fedora, arch
     * ID_LIKE = space separated fallback family, e.g. "debian" for mint/kali
     */
    private static function detectLinuxDistro():array{
        $result = ['id' => 'linux', 'id_like' => [], 'version' => null, 'major' => null];

        if (is_readable('/etc/os-release')) {
            $data = parse_ini_file('/etc/os-release') ?: [];

            if (!empty($data['ID'])) {
                $result['id'] = strtolower($data['ID']);
            }
            if (!empty($data['ID_LIKE'])) {
                $result['id_like'] = array_map('strtolower', explode(' ', $data['ID_LIKE']));
            }
            if (!empty($data['VERSION_ID'])) {
                $result['version'] = $data['VERSION_ID'];
                $result['major']   = self::majorVersion($data['VERSION_ID']);
            }
        }

        return $result;
    }

    /**
     * Windows 10 vs 11 both report kernel "10.0.x" - the only reliable
     * signal is the build number. 22000+ = Windows 11.
     */
    private static function detectWindows():array{
        $build = null;

        $out = shell_exec('reg query "HKLM\SOFTWARE\Microsoft\Windows NT\CurrentVersion" /v CurrentBuildNumber 2>NUL');
        if ($out && preg_match('/CurrentBuildNumber\s+REG_SZ\s+(\d+)/i', $out, $m)) {
            $build = (int) $m[1];
        }

        // Fallback if registry query fails for some reason
        if ($build === null) {
            $release = php_uname('r'); // e.g. "10.0.22631"
            if (preg_match('/(\d+)$/', $release, $m)) {
                $build = (int) $m[1];
            }
        }

        $version = ($build !== null && $build >= 22000) ? '11' : '10';

        return ['version' => $version, 'build' => $build];
    }
}
/**
 * Similar to mklog() but if mklog is not defined it just echoes the message and type.
 *
 * @param integer $type The numerical message type, see mklog().
 * @param string $message The message to display / log if possible.
 * @return void
 */
function mklog0(int $type, string $message):void{
    if(function_exists('mklog')){
        mklog($type, $message);
    }
    else{
        $type = min(max($type,0),3);
        if($type){
            echo date("Y-m-d_H:i:s:") . substr(floor(microtime(true)*1000), -3) . ": " . ["General","Warning","Error"][$type-1] . ": " . $message . "\n";
        }
    }
}
/**
 * Downloads a CA bundle for curl from https://curl.se/ca/cacert.pem using file_get_contents.
 * @param string $destPath The path to put the certificate file.
 * @return bool Indicates success.
 */
function downloadCaBundle(string $destPath):bool{
    $data = @file_get_contents('https://curl.se/ca/cacert.pem');

    if(!is_string($data)){
        mklog0(2, "Failed to download CA bundle.");
        return false;
    }

    if(strlen($data) < 100000 || !str_contains($data, '-----BEGIN CERTIFICATE-----')){
        mklog0(2, "Downloaded file does not look like a valid CA bundle.");
        return false;
    }

    return (bool) @file_put_contents($destPath, $data);
}