<?php
pkgmgr::init();

/**
 * The package manager.
 */
class pkgmgr{
    private static $packageCount = 0;
    private static $packageInitCount = 0;
    private static $packages = [];
    private static $curlHandle = null;
    private static $authenticated = null;

    /**
     * @internal
     */
    public static function command(string $line):void{
        $command = cli::parseLine($line);

        if($command['args'][0] === "list"){
            $columnNames["Package ID"] = 25;
            $columnNames["Package Name"] = 35;
            $columnNames["Version"] = 8;
            if(in_array("desc", $command['options'])){
                $columnNames["Package Description"] = 40;
            }
            if(in_array("deps", $command['options'])){
                $columnNames["Package Dependencies"] = 40;
            }

            $rowsData = array();
            foreach(self::$packages as $packageId => $packageInfo){
                $rowData = array();
                $rowData[] = $packageId;
                $rowData[] = $packageInfo['name'];
                $rowData[] = $packageInfo['version'];

                if(in_array("desc", $command['options'])){
                    $rowData[] = $packageInfo['description'];
                }
                if(in_array("deps", $command['options'])){
                    $dependenciesString = count($packageInfo['dependencies']) . ": ";
                    ksort($packageInfo['dependencies']);
                    foreach($packageInfo['dependencies'] as $dependencyId => $dependencyVersion){
                        $dependenciesString .= $dependencyId . ", ";
                    }
                    $rowData[] = substr($dependenciesString,0,-2);
                }

                $rowsData[] = $rowData;
            }
            echo commandline_list::table($columnNames,$rowsData);
        }
        elseif($command['args'][0] === "install"){
            array_shift($command['args']);
            if(empty($command['args'])){
                echo "Package id not specified\n";
            }
            foreach($command['args'] as $package){
                $parts = explode("-", $package);
                $packageId = $parts[0];
                $version = $parts[1] ?? null;

                if($packageId === "php_cli"){
                    continue;
                }
                if(!in_array("overwrite", $command['options']) && self::isPackageDownloaded($packageId)){
                    echo "Package $packageId is already downloaded\n";
                    continue;
                }
                if(!self::downloadPackage($packageId, $version)){
                    echo "Failed to download package $packageId-$version\n";
                }
            }
        }
        elseif($command['args'][0] === "update"){
            array_shift($command['args']);
            if(!self::updatePackages($command['args'])){
                echo "Failed to update all packages\n";
            }
        }
        elseif($command['args'][0] === "login"){
            if(!isset($command['args'][1])){
                echo "Username not specified\n";
                return;
            }
            if(!isset($command['args'][2])){
                echo "Password not specified\n";
                return;
            }

            if(!self::authenticate($command['args'][1], $command['args'][2])){
                echo "Failed to authenticate\n";
                return;
            }

            echo "Authenticated\n";
        }
        else{
            echo "Command not found\n";
        }
    }
    /**
     * @internal
     */
    public static function init():void{
        if(!is_dir('packages')){
            if(!mkdir('packages',0777,true)){
                mklog(3,'Failed to create packages directory');
            }
        }

        if(!is_file("packages/.updated")){
            mklog(1, "Updating package info format");
            foreach(glob('packages/*/information.json') ?? [] as $infoFile){
                $oldInfo = json::readFile($infoFile);
                if(!is_array($oldInfo)){
                    mklog(2, "Failed to read file $infoFile");
                    continue;
                }

                $newInfo = [
                    'id' => $oldInfo['id_name'],
                    'name' => $oldInfo['name'],
                    'version' => "1." . $oldInfo['version'] . ".0",
                    'author' => $oldInfo['author'],
                    'description' => $oldInfo['description'],
                    'operatingSystems' => ["windows"],
                    'dependencies' => []
                ];
                foreach($oldInfo['dependencies'] as $dependencyId => $dependencyVersion){
                    $newInfo['dependencies'][] = [
                        "id" => $dependencyId,
                        "min" => "1." . $dependencyVersion . ".0"
                    ];
                }

                if(!json::writeFile($infoFile, $newInfo, true)){
                    mklog(2, "Failed to write filr $infoFile");
                    continue;
                }
            }
            files::mkFile("packages/.updated", "1");
        }
        
        foreach(glob('packages/*') ?? [] as $dir){
            $package = basename($dir);
            if(!class_exists($package)){//Other packages may have loaded package as a dependency
                if(!self::loadPackage($package)){
                    mklog(3,'Unable to load package ' . $package);
                }
            }
        }
        
        mklog(1,'Loaded ' . self::$packageCount . ' packages, ' . self::$packageInitCount . ' initialized');
    }

    /**
     * Loads a package.
     *
     * @param string $packageId The id of the package to be loaded.
     * @return boolean Indicates success.
     */
    public static function loadPackage(string $packageId):bool{
        if(!self::isValidPackageId($packageId)){
            mklog(2, 'Invalid package id');
            return false;
        }
        
        if(in_array($packageId, PHP_CLI_PRELOAD_PKGS) || $packageId === "php_cli"){
            return true;
        }

        mklog(0, 'Loading package ' . $packageId);

        if(class_exists($packageId) || isset(self::$packages[$packageId])){
            mklog(0,'Package ' . $packageId . ' is allready loaded');
            return true;
        }

        $info = self::localPackageInfo($packageId);
        if(!is_array($info)){
            mklog(3, 'Failed to get package info while loading package ' . $packageId);
            return false;
        }

        if(isset($info['minCliVersion'])){
            if(version_compare(PHP_CLI_VERSION, $info['minCliVersion'], "<")){
                mklog(3, "Failed to load package $packageId because it requires PHP-CLI version " . $info['minCliVersion'] . " or higher");
                return false;
            }
        }
        if(isset($info['minPhpVersion'])){
            if(version_compare(phpversion(), $info['minPhpVersion'], "<")){
                mklog(3, "Failed to load package $packageId because it requires PHP-CLI version " . $info['minPhpVersion'] . " or higher");
                return false;
            }
        }
        if(isset($info['operatingSystems']) && !empty($info['operatingSystems'])){
            if(!sysenv::matches($info['operatingSystems'])){
                mklog(3, "Failed to load package $packageId because it is not compatible with your detected operating system");
                return false;
            }
        }
        if(isset($info['architectures']) && !empty($info['architectures'])){
            $systemArch = sysenv::architecture();
            $matchFound = false;
            foreach($info['architectures'] as $arch){
                if($systemArch === $arch){
                    $matchFound = true;
                    break;
                }
            }

            if(!$matchFound){
                mklog(3, "Failed to load package $packageId because it is not compatible with your detected system architecture");
                return false;
            }
        }

        foreach($info['dependencies'] as $dependency){
            if(in_array($dependency['id'], PHP_CLI_PRELOAD_PKGS) || $dependency['id'] === "php_cli"){
                continue;
            }

            $localDependencyInfo = self::localPackageInfo($dependency['id']);
            if(!is_array($localDependencyInfo)){
                if($dependency['optional'] ?? false){
                    mklog(0, "Optional dependency " . $dependency['id'] . " for $packageId is not installed");
                    continue;
                }
                mklog(3, "Failed to load package $packageId because one of its dependencies (" . $dependency['id'] . ") is not installed");
                return false;
            }

            if(version_compare($localDependencyInfo['version'], $dependency['min'], "<")){
                if($dependency['optional'] ?? false){
                    mklog(0, "Optional dependency " . $dependency['id'] . " for $packageId is below the minimum requested version");
                    continue;
                }
                mklog(3, "Failed to load package $packageId because one of its dependencies (" . $dependency['id'] . ") is installed but is below the minimum required version");
                return false;
            }
        }
        foreach($info['dependencies'] as $dependency){
            if(!self::loadPackage($dependency['id'])){
                if($dependency['optional'] ?? false){
                    mklog(0, "Optional dependency " . $dependency['id'] . " for $packageId failed to load");
                    continue;
                }
                mklog(3, "Failed to load package $packageId because one of its dependencies (" . $dependency['id'] . ") failed to load");
                return false;
            }
        }

        if(!is_file($info['dir'] . "/main.php")){
            mklog(3, "Failed to load package $packageId because its missing its main.php file");
            return false;
        }

        if($GLOBALS['arguments']['check-syntax']){
            if(!self::isPhpFileSyntaxOk($info['dir'] . "/main.php")){//slow
                mklog(3, "Failed to load package $packageId because its main.php file has invalid syntax");
                return false;
            }
        }

        include_once $info['dir'] . "/main.php";
        self::$packageCount++;
        self::$packages[$packageId] = $info;

        if(method_exists($packageId, "init")){
            mklog(1, "Running init for package $packageId");
            $initReturn = null;
            try{
                $initReturn = $packageId::init();
                self::$packageInitCount++;
            }
            catch(Throwable $throwable){
                mklog(3, "Failed to run init for package $packageId (" . substr($throwable,0,strpos($throwable,"\n")) . ")");
                return false;
            }

            if(is_bool($initReturn)){
                if($initReturn === false){
                    mklog(3, "Package $packageId reported an unsuccessful init");
                    return false;
                }
            }
        }

        return true;
    }
    /**
     * Downloads a package.
     *
     * @param string $packageId The id of the package to download.
     * @param integer|boolean $version The version to download or false, which makes it download the latest version.
     * @param boolean $getDependencies Weather to also download the packages dependencies.
     * @param boolean $load Weather to load the package after it has been downloaded.
     * @return boolean Indicates success.
     */
    public static function downloadPackage(string $packageId, ?string $version=null, bool $getDependencies=true, bool $load=true):bool{
        if(!self::isValidPackageId($packageId)){
            mklog(2, "Invalid package id $packageId");
            return false;
        }

        if(!is_string($version)){
            $mainInfo = self::onlinePackageInfo($packageId);
            if(!is_array($mainInfo) || !isset($mainInfo['latestVersion'])){
                mklog(3, "Failed to get latest version info for package $packageId");
                return false;
            }
            $version = $mainInfo['latestVersion'];
        }
        
        if(!self::isValidVersion($version)){
            mklog(2, "Invalid package version $version");
            return false;
        }

        $localPackageInfo = self::localPackageInfo($packageId);
        if(is_array($localPackageInfo)){
            if($localPackageInfo['version'] === $version){
                mklog(1, "The installed version of $packageId already matches");
                return true;
            }
        }
        
        $info = self::packageVersionInfo($packageId, $version);
        if(!is_array($info)){
            mklog(3, "Failed to get version info for package $packageId-$version");
            return false;
        }

        $downloadFile = 'temp/pkgmgr/downloads/' . $packageId . '-' . round(microtime(true)*1000) . '.zip';
        mklog(1, "Downloading package $packageId-$version");

        if(!downloader::downloadFile($info['downloadUrl'], $downloadFile)){
            mklog(3, "Failed to download zip file for package $packageId-$version from " . $info['downloadUrl']);
            return false;
        }

        $zip = new ZipArchive;
        $result = $zip->open($downloadFile);
        if($result !== true){
            mklog(3, "Failed to download a valid zip file for package $packageId-$version");
            return false;
        }
        $zip->close();

        if($getDependencies){
            mklog(0, 'Downloading dependencies for package ' . $packageId);
            
            foreach($info['dependencies'] as $dependency){
                $localDependencyInfo = self::localPackageInfo($dependency['id']);
                if(is_array($localDependencyInfo)){
                    if(version_compare($localDependencyInfo['version'], $dependency['min'], ">=")){
                        continue;
                    }
                }

                if(!self::downloadPackage($dependency['id'])){
                    mklog(3, "Failed to download dependency " . $dependency['id'] . "-" . $dependency['min'] . " for package $packageId-$version");
                }
            }
        }

        if($packageId !== "php_cli"){
            if(is_dir('packages/' . $packageId . '/files')){
                if(!cmd::run('rmdir "packages/' . $packageId . '/files" /S /Q')){
                    mklog(2, "Failed to remove old files directory for package $packageId");
                }
            }

            files::ensureFolder('packages/' . $packageId);
        }

        mklog(0, "Unpacking zip for $packageId-$version");
        $zip = new ZipArchive;
        $result = $zip->open($downloadFile);
        if($result !== true){
            mklog(3, 'Failed to open package zip file ' . $downloadFile);
            return false;
        }

        if(!$zip->extractTo(($packageId === "php_cli" ? "." : "packages/$packageId"))){
            mklog(3, "Failed to extract contents from package $packageId-$version zip file");
            return false;
        }

        $zip->close();

        if($packageId === "php_cli"){
            mklog(1, "Restarting php_cli to apply update");
            cli::run("cli reload");
            exit;
        }

        if(!json::writeFile('packages/' . $packageId . '/information.json', $info, true)){
            mklog(3,'Unable to write information file for package ' . $packageId);
            return false;
        }

        if(!unlink($downloadFile)){
            mklog(0,'Unable to delete temporary download file ' . $downloadFile);
        }

        if($load){
            mklog(1, "Loading " . $packageId);
            return self::loadPackage($packageId);
        }
        
        mklog(1, 'Please restart PHP-CLI for the update to apply');
        return true;
    }
    /**
     * Updates specified packages or all packages.
     * @param array $packages A list of package ids or all packages when empty. The .noupdate file is only respected when $packages is empty.
     * @return bool Weather all packages are updated, stops on failure.
     */
    public static function updatePackages(array $packages=[]):bool{
        if(empty($packages)){
            $packagePaths = glob('packages/*/information.json');
            if(!is_array($packagePaths)){
                mklog(2, 'Unable to read packages folder');
                return false;
            }
            foreach($packagePaths as $packagePath){
                $packageDir = dirname($packagePath);
                
                if(is_file($packageDir . "/.noupdate")){
                    continue;
                }
                $packages[] = basename($packageDir);
            }
        }

        foreach($packages as $package){
            if(self::isPackageDownloaded($package)){
                return false;
            }

            if(!self::downloadPackage($package, null, true, false)){
                mklog(2, 'Failed to update package ' . $package,false);
                return false;
            }
        }

        return true;
    }
    /**
     * Checks weather a package is downloaded.
     * @param string $packageId The package id.
     * @return bool Weather the package is downloaded.
     */
    public static function isPackageDownloaded(string $packageId):bool{
        return self::isValidPackageId($packageId) && is_file("packages/$packageId/information.json") && is_file("packages/$packageId/main.php");
    }
    /**
     * Reads the main.php file in a package and checks for what classes it uses.
     *
     * @param string $packageId The id of the package.
     * @return array|false An estimation of the dependencies on success or false on failure.
     */
    public static function getPackageFileDependencies(string $packageId):?array{
        if(!self::isValidPackageId($packageId)){
            return null;
        }

        if(in_array($packageId, PHP_CLI_PRELOAD_PKGS) || $packageId === "php_cli"){
            return [];
        }

        $file = 'packages/' . $packageId . '/main.php';
        if(!is_file($file)){
            return null;
        }

        $text = file_get_contents($file);
        if($text === false){
            mklog(2,'Unable to read package file ' . $file);
        }
        $offset = 0;
        $dependencies = [];
        while(true){
            $pos = strpos($text,"::",$offset);
            if($pos === false){
                break;
            }
            $pos2 = 1;
            while(true){
                $dependency = substr($text,$pos - $pos2,$pos2);
                if(preg_match("/^[a-zA-Z0-9_]+$/", $dependency) === 1){
                    $pos2++;
                }
                else{
                    break;
                }
            }
            $dependency = trim(substr($dependency,1));
            if(!in_array($dependency, PHP_CLI_PRELOAD_PKGS) && $dependency !== "php_cli" && is_file('packages/' . $dependency . '/main.php')){
                $leftfromdependency = 1;
                $makedependency = true;
                while(true){
                    $firsttwochars = substr($text,$pos - $pos2 - $leftfromdependency,2);
                    if($firsttwochars === "//"){
                        $makedependency = false;
                        break;
                    }
                    elseif(strpos($firsttwochars,"\n") !== false){
                        break;
                    }
                    else{
                        $leftfromdependency++;
                    }
                }
                
                if($makedependency){
                    if(!in_array($dependency, $dependencies)){
                        $dependencies[] = $dependency;
                    }
                }
            }
            $offset = $pos+2;
        }

        return $dependencies;
    }
    /**
     * Checks if a string could be a valid package id, this does not check package existance.
     *
     * @param string $package The string to be tested.
     * @return boolean True if the string is valid, false otherwise.
     */
    public static function isValidPackageId(string $packageId):bool{
        return (strlen($packageId) > 2 && preg_match('/^[a-z_][a-z0-9_]*$/', $packageId));
    }
    /**
     * Checks weather a version string is in x.x.x format or not.
     * @param string $version The version string.
     * @return bool Weather the version string is valid.
     */
    public static function isValidVersion(string $version):bool{
        return (bool) preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/', $version);
    }
    /**
     * Gets package information from the configured packages api.
     * @param string $package The package to get info for.
     * @return null|array The info on success or null on failure.
     */
    public static function onlinePackageInfo(string $packageId):?array{
        if(!self::isValidPackageId($packageId)){
            return null;
        }

        $response = self::apiRequest(PHP_CLI_PKG_API_URL . "/php-cli/api/?function=packageInfo&package=$packageId");
        if(!is_string($response)){
            mklog(2, "Failed to contact packages api");
            return null;
        }

        $info = json_decode($response, true);
        if(!is_array($info) || !isset($info['id'])){
            mklog(2, "Getting online package info for $packageId failed with error: " . ($info['error'] ?? "Unknown error"));
            return null;
        }

        return $info;
    }
    /**
     * Gets version specific information for a package from online.
     * @param string $packageId The package id.
     * @param string $version The version of the package.
     * @return null|array The info on success or false on failure.
     */
    public static function packageVersionInfo(string $packageId, string $version):?array{
        if(!self::isValidPackageId($packageId) || !self::isValidVersion($version)){
            return null;
        }

        $response = self::apiRequest(PHP_CLI_PKG_API_URL . "/php-cli/api/?function=packageVersionInfo&package=$packageId&version=$version");
        if(!is_string($response)){
            mklog(2, "Failed to contact packages api");
            return null;
        }
        
        $info = json_decode($response, true);
        if(!is_array($info) || !isset($info['version'])){
            mklog(2, "Getting online package version info for $packageId failed with error: " . ($info['error'] ?? "Unknown error"));
            return null;
        }

        return $info;
    }
    /**
     * Gets the information for a local/installed package.
     * required data: id, version, author, name, dependencies.
     * optional data: minCliVersion, minPhpVersion, operatingSystems, architectures, globalName, description, creationTime, downloadTime. 
     * @param string $packageId The package id.
     * @return null|array The info on success or null on failure.
     */
    public static function localPackageInfo(string $packageId):?array{
        if(!self::isValidPackageId($packageId)){
            return null;
        }

        $infoFile = 'packages/' . $packageId . '/information.json';
        $phpMainFile = 'packages/' . $packageId . '/main.php';

        if(!is_file($infoFile) || !is_file($phpMainFile)){
            mklog(2, 'The package ' . $packageId . ' has missing files');
            return null;
        }

        $packageInfo = json::readFile($infoFile);
        if(!is_array($packageInfo)){
            mklog(2,'Unable to load information for package ' . $packageId);
            return null;
        }

        $packageInfo['dir'] = getcwd() . '/packages/' . $packageId;

        if(!self::isValidLocalPackageInfo($packageInfo)){
            mklog(2,'The package ' . $packageId . ' has invalid data');
            return null;
        }

        return $packageInfo;
    }
    /**
     * Tests weather local package info is valid.
     * required data: id, version, author, name, dependencies.
     * optional data: minCliVersion, minPhpVersion, operatingSystems, architectures, globalName, description, creationTime, downloadTime. 
     * @param mixed $info The package info to test.
     * @return bool Weather its valid info.
     */
    public static function isValidLocalPackageInfo(mixed $info):bool{
        if(!is_array($info)){
            return false;
        }

        if(!is_string($info['id'] ?? null) || !self::isValidPackageId($info['id'])){
            return false;
        }
        if(!is_string($info['version'] ?? null) || !self::isValidVersion($info['version'])){
            return false;
        }
        if(!is_string($info['author'] ?? null) || !preg_match('/^[a-z0-9_]{2,20}$/', $info['author'])){
            return false;
        }
        if(!is_string($info['name'] ?? null) || empty($info['name'])){
            return false;
        }
        if(!is_string($info['dir'] ?? null) || empty($info['dir'])){
            return false;
        }

        if(!is_array($info['dependencies'] ?? null)){
            return false;
        }
        foreach($info['dependencies'] as $dependency){
            if(!is_array($dependency)){
                return false;
            }
            if(!is_string($dependency['id'] ?? null) || !self::isValidPackageId($dependency['id'])){
                return false;
            }
            if(!is_string($dependency['min'] ?? null) || !self::isValidVersion($dependency['min'])){
                return false;
            }
            if(isset($dependency['optional']) && !is_bool($dependency['optional'])){
                return false;
            }
        }

        foreach(['minCliVersion', 'minPhpVersion'] as $thing){
            if(isset($info[$thing])){
                if(!is_string($info[$thing]) || !self::isValidVersion($info[$thing])){
                    return false;
                }
            }
        }
        foreach(['operatingSystems', 'architectures'] as $thing){
            if(isset($info[$thing])){
                if(!is_array($info[$thing]) || !array_is_list($info[$thing])){
                    return false;
                }
                foreach($info[$thing] as $item){
                    if(!is_string($item) || empty($item)){
                        return false;
                    }
                }
            }
        }
        foreach(['globalName', 'description', 'globalDescription'] as $thing){
            if(isset($info[$thing])){
                if(!is_string($info[$thing])){
                    return false;
                }
            }
        }
        foreach(['creationTime', 'downloadTime'] as $thing){
            if(isset($info[$thing])){
                if(!is_int($info[$thing]) || $info[$thing] < 0){
                    return false;
                }
            }
        }
        
        return true;
    }

    /**
     * Gets all the loaded packages and their versions.
     *
     * @return array
     */
    public static function getLoadedPackages():array{
        $return = [];
        foreach(self::$packages as $packageId => $packageInfo){
            $return[$packageId] = $packageInfo['version'];
        }
        return $return;
    }
    /**
     * Gets the download url for the latest thread safe x64 windows build of php from windows.php.net.
     *
     * @return string|false The url of the latest zip or false on failure.
     */
    public static function getLatestPhpUrl():string|false{
        $data = json::readFile('https://downloads.php.net/~windows/releases/releases.json');

        if(!is_array($data)){
            mklog(2,'Unable to read php releases information');
            return false;
        }
        
        //Major version search
        $currentVersion = "0";
        foreach($data as $version => $versionData){
            if(floatval($version) > floatval($currentVersion)){
                $currentVersion = $version;
            }
        }

        if(!isset($data[$currentVersion])){
            return false;
        }
        $data = $data[$currentVersion];

        //Type search
        $currentVersion = "";
        foreach($data as $version => $versionData){
            if(substr($version,0,3) === "ts-" && substr($version, -4) === "-x64"){
                $currentVersion = $version;
                break;
            }
        }

        if(!isset($data[$currentVersion])){
            return false;
        }
        $data = $data[$currentVersion];

        //Final check
        if(!isset($data['zip'])){
            return false;
        }
        if(!isset($data['zip']['path'])){
            return false;
        }

        return 'https://downloads.php.net/~windows/releases/' . $data['zip']['path'];
    }
    /**
     * Checks the syntax of a php file with php -l.
     *
     * @param string $file The php file to be checked.
     * @return boolean Weather the php file has valid syntax.
     */
    public static function isPhpFileSyntaxOk(string $file):bool{

        $output = shell_exec("php -l " . escapeshellarg($file) . " 2>&1");

        return str_contains($output, 'No syntax errors');
    }
    /**
     * Logs into the set api website.
     * @param string $uid The username to log into.
     * @param string $pwd The password for the username.
     * @return bool Weather the login was a success.
     */
    public static function authenticate(string $uid, string $pwd):bool{
        if(self::$authenticated){
            mklog(1, "You are already authenticated as " . self::$authenticated);
            return true;
        }

        $response = self::apiRequest(PHP_CLI_PKG_API_URL . "/api/?function=loginSubmit", ["uid"=>$uid, "pwd"=>$pwd]);
        if(!is_string($response)){
            mklog(2, "Failed to contact login endpoint");
            return false;
        }

        $result = json_decode($response, true);
        if(!is_array($result) || !isset($result['success'])){
            mklog(2, "Login endpoint returned unexpected data");
            return false;
        }

        if(!$result['success']){
            mklog(2, "Login endpoint returned an error: " . ($result['error'] ?? "Unknown error"));
            return false;
        }

        if(empty($result['useruid'] ?? "")){
            mklog(2, "Login endpoint did not provide a useruid");
            return false;
        }

        self::$authenticated = $result['useruid'];
        return true;
    }

    private static function apiRequest(string $url, ?array $postData=null, array $extraHeaders=[]):?string{
        if(self::$curlHandle === null){
            self::$curlHandle = curl_init();
            curl_setopt(self::$curlHandle, CURLOPT_COOKIEFILE, ''); // enable in-memory cookie engine
            curl_setopt(self::$curlHandle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt(self::$curlHandle, CURLOPT_FOLLOWLOCATION, true);
        }

        curl_setopt(self::$curlHandle, CURLOPT_URL, $url);
        curl_setopt(self::$curlHandle, CURLOPT_HTTPHEADER, $extraHeaders);

        if($postData !== null){
            curl_setopt(self::$curlHandle, CURLOPT_POST, true);
            curl_setopt(self::$curlHandle, CURLOPT_POSTFIELDS, $postData);
        }
        else{
            curl_setopt(self::$curlHandle, CURLOPT_HTTPGET, true); // resets POST automatically
        }

        $response = curl_exec(self::$curlHandle);
        if(curl_errno(self::$curlHandle)){
            mklog(2, 'cURL error: ' . curl_error(self::$curlHandle));
            return null;
        }

        return $response;
    }
}