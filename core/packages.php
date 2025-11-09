<?php
pkgmgr::init();

class pkgmgr{
    private static $downloadSite = 'https://www.tomgriffiths.net';
    private static $downloadSiteFiles = 'https://files.tomgriffiths.net';
    private static $packageCount = 0;
    private static $packageInitCount = 0;
    private static $packages = [];
    public static function command($line):void{
        $lines = explode(" ",$line);
        if($lines[0] === "list"){
            $columnNames["Package ID"] = 25;
            $columnNames["Package Name"] = 35;
            if(isset($lines[1])){
                if($lines[1] === "desc"){
                    $columnNames["Package Description"] = 40;
                }
                elseif($lines[1] === "depend"){
                    $columnNames["Package Dependencies"] = 40;
                }
            }
            $columnNames["Version"] = 8;

            $rowsData = array();
            foreach(self::$packages as $packageId => $packageInfo){
                $rowData = array();
                $rowData[] = $packageId;
                $rowData[] = $packageInfo['name'];
                if(isset($lines[1])){
                    if($lines[1] === "desc"){
                        $rowData[] = $packageInfo['description'];
                    }
                    elseif($lines[1] === "depend"){
                        $dependenciesString = count($packageInfo['dependencies']) . ": ";
                        ksort($packageInfo['dependencies']);
                        foreach($packageInfo['dependencies'] as $dependencyId => $dependencyVersion){
                            $dependenciesString .= $dependencyId . ", ";
                        }
                        $rowData[] = substr($dependenciesString,0,-2);
                    }
                }
                
                $rowData[] = $packageInfo['version'];
                $rowsData[] = $rowData;
            }
            echo commandline_list::table($columnNames,$rowsData);
        }
        elseif($lines[0] === "install"){
            if(!isset($lines[1])){
                echo "pkgmgr: Package id not specified\n";
            }
            if(!self::downloadPackage($lines[1])){
                echo "\npkgmgr: Failed to download package " . $lines[1] . "\n\n";
            }
        }
        elseif($lines[0] === "update"){
            if(isset($lines[1])){
                if(self::doesPackageExist($lines[1],false)){
                    if(!self::downloadPackage($lines[1],false,false,false)){
                        mklog('warning','Failed to update package ' . $lines[1],false);
                    }
                    else{
                        mklog('general','Please restart for changes to take effect',false);
                    }
                }
            }
            else{
                self::updatePackages();
            }
        }
        elseif($lines[0] === "update-core"){
            mklog('general','Running Update',false);
            files::ensureFolder("temp\\coreupdates");
            if(!downloader::downloadFile("https://files.tomgriffiths.net/php-cli/updates/latest.zip","temp\\coreupdates\\latest.zip")){
                echo "Failed to download update file\n";
                goto end;
            }

            $zip = new ZipArchive;
            $result = $zip->open("temp\\coreupdates\\latest.zip");
            if($result === true){
                $zip->extractTo("temp\\coreupdates\\latest");
                $zip->close();
            }
            else{
                echo "Failed to unzip update file\n";
                goto end;
            }

            $updateFiles = glob("temp\\coreupdates\\latest");
            foreach($updateFiles as $file){
                if(is_file($file)){
                    files::copyFile($file,files::getFileName($file));
                    unlink($file);
                }
            }

            $robocopyFrom = files::validatePath(getcwd() . "\\temp\\coreupdates\\latest\\core",true);
            $robocopyTo = files::validatePath(getcwd() . "\\core",true);
            cmd::run('robocopy ' . $robocopyFrom . ' ' . $robocopyTo . ' /e /v /mir');
            
            cmd::run('rmdir ' . files::validatePath(getcwd() . "\\temp\\coreupdates",true) . ' /s /q');

            cmd::newWindow('"' . getcwd() . '\php\php.exe" "' . getcwd() . '\cli.php" after-update true"');
            exit;
            end:
        }
        else{
            echo "pkgmgr: Command not found\n";
        }
    }
    public static function init():void{
        if(!is_dir('packages')){
            if(!mkdir('packages',0777,true)){
                mklog(3,'Failed to create packages directory');
            }
        }
        
        foreach(glob('packages/*') as $dir){
            $package = substr($dir,strripos($dir,"/")+1);
            if(!class_exists($package)){//Other packages may have loaded package as a dependency
                if(self::loadPackage($package) !== true){
                    mklog(2,'Unable to load package ' . $package,false);
                }
            }
        }
        
        mklog(1,'Loaded ' . self::$packageCount . ' packages, ' . self::$packageInitCount . ' initialized');
    }
    public static function loadPackage(string $package, int|bool $version = false):bool{
        if(self::validatePackageId($package)){
            $preloadedPackages = ["self","cli","cli_formatter","cmd","commandline_list","data_types","downloader","extensions","files","json","pkgmgr","time","timetest","txtrw","user_input"];
            if(in_array($package,$preloadedPackages)){
                return true;
            }

            if(!class_exists($package)){
                $info = self::getPackageInfo($package,false);
                if(is_int($version)){
                    if($info['version'] > $version){
                        mklog(2,'Unable to load package ' . $package . ' version ' . $version . ' because version ' . $info['version'] . ' is installed');
                        return false;
                    }
                }
                if(is_array($info)){
                    foreach($info['dependencies'] as $dependencyId => $dependencyVersion){
                        if(!in_array($dependencyId,$preloadedPackages)){
                            $dependencyInfo = self::getPackageInfo($dependencyId,false);
                            if(is_array($dependencyInfo)){
                                if($dependencyInfo['version'] < $dependencyVersion){
                                    mklog(2,'Unable to load package ' . $package . ' as one of its dependencies (' . $dependencyId . ') has an incorrect version of ' . $dependencyVersion);
                                    return false;
                                }
                            }
                            else{
                                mklog(2,'Unable to load package ' . $package . ' as one of its dependencies (' . $dependencyId . ') does not exist');
                                return false;
                            }
                        }
                    }
                    foreach($info['dependencies'] as $dependencyId => $dependencyVersion){
                        if(!class_exists($dependencyId)){
                            if(!self::loadPackage($dependencyId)){
                                mklog(2,'Unable to load dependency ' . $dependencyId . ' for ' . $package);
                                return false;
                            }
                        }
                    }

                    mklog(0,'Loading package ' . $package);

                    if(is_file($info['dir'] . "\\main.php")){
                        include_once $info['dir'] . "\\main.php";
                        self::$packageCount++;
                        if(method_exists($info['id_name'],"init")){
                            mklog(1,'Running init for package ' . $info['name']);
                            $info['id_name']::init();
                            self::$packageInitCount++;
                        }
                        self::$packages[$package] = $info;
                        return true;
                    }
                }
                else{
                    mklog(2,'Package ' . $package . ' does not exist');
                }
            }
            else{
                mklog(1,'Package ' . $package . ' is allready loaded');
            }
        }
        return false;
    }
    public static function validatePackageId(string $packageId):bool{
        return (preg_match("/^[a-zA-Z0-9_]+$/",$packageId) === 1);
    }
    public static function validatePackageInfo($info):bool{
        if(isset($info['id_name'])){
            if(self::validatePackageId($info['id_name'])){
                if(isset($info['version'])){
                    if(is_int($info['version'])){
                        if($info['version'] > 0){
                            if(isset($info['author'])){
                                if(is_string($info['author'])){
                                    if(preg_match("/[a-z0-9_]/", $info['author']) === 1){
                                        if(isset($info['name'])){
                                            if(is_string($info['name'])){
                                                if(isset($info['dependencies'])){
                                                    if(is_array($info['dependencies'])){
                                                        foreach($info['dependencies'] as $key => $value){
                                                            if(!self::validatePackageId($key) || !is_int($value)){
                                                                return false;
                                                            }
                                                        }
                                                        return true;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return false;
    }
    public static function getPackageDependencies(array $packageInfo):array|bool{
        if(self::validatePackageInfo($packageInfo)){
            return $packageInfo['dependencies'];
        }
        return false;
    }
    public static function doesPackageExist(string $packageId, bool $online):bool{
        if(self::getPackageInfo($packageId,$online) !== false){
            return true;
        }
        return false;
    }
    public static function getPackageInfo(string $packageId, bool $online):bool|array{
        if(self::validatePackageId($packageId)){
            if($online === true){
                $result = json::readFile(self::$downloadSite . '/php-cli/api/?function=getPackageInfo&packageId=' . $packageId,false);
                if(!is_array($result)){
                    mklog(2,'Failed to download information for package ' . $packageId);
                    return false;
                }
                if(isset($result['success'])){
                    if($result['success'] === true){
                        return $result['data'];
                    }
                }
            }
            else{
                $infoFile = 'packages/' . $packageId . '/information.json';
                $phpMainFile = 'packages/' . $packageId . '/main.php';
                if(is_file($infoFile) && is_file($phpMainFile)){
                    $packageInfo = json::readFile($infoFile,false);
                    if(!is_array($packageInfo)){
                        mklog(2,'Unable to load information for package ' . $packageId);
                        return false;
                    }
                    $packageInfo['dir'] = getcwd() . '\\packages\\' . $packageId;
                    if(self::validatePackageInfo($packageInfo)){
                        return $packageInfo;
                    }
                }
            }
        }
        return false;
    }
    public static function getPackageVersionInfo(string $packageId, int $version):bool|array{
        if(self::validatePackageId($packageId)){
            $result = json::readFile(self::$downloadSite . '/php-cli/api/?function=getPackageVersionInfo&packageId=' . $packageId . '&version=' . $version,false);
            if(!is_array($result)){
                mklog(2,'Unable to download information for package ' . $packageId . ' v' . $version);
                return false;
            }
            if(isset($result['success'])){
                if($result['success'] === true){
                    return $result['data'];
                }
            }
        }
        return false;
    }
    public static function downloadPackage(string $packageId, int|bool $version = false, bool $getDependencies = true, bool $load = true):bool{
        if(self::validatePackageId($packageId)){

            $info = self::getPackageInfo($packageId,true);

            if(!is_array($info)){
                mklog(2,'Package ' . $packageId . ' does not exist');
                return false;
            }

            foreach(array('id_name','author','versions','name','latest_version') as $thing){
                if(!isset($info[$thing])){
                    mklog(2,'Incomplete data for download of ' . $packageId);
                    return false;
                }
            }

            $downloadVersion = false;
            if(is_int($version)){
                if(in_array($version,$info['versions'])){
                    $downloadVersion = $version;
                }
            }
            if(!is_int($downloadVersion)){
                $downloadVersion = $info['latest_version'];
            }

            if(is_file('packages/' . $packageId . '/information.json')){
                $localInfo = json::readFile('packages/' . $packageId . '/information.json');
                if(!is_array($localInfo)){
                    mklog(1,'Local package information for package ' . $packageId . ' is not correct, overwriting');
                }
                if(isset($localInfo['version'])){
                    if($localInfo['version'] == $downloadVersion){
                        mklog(1,'Version of package ' . $packageId . ' already matches');
                        return true;
                    }
                }
            }

            if(is_file('packages/' . $packageId . '/.noupdate')){
                mklog(2,'Package ' . $packageId . ' is marked for not updating (.noupdate file found)');
                return false;
            }

            if(is_dir('packages/' . $packageId . '/files')){
                if(!cmd::run('rmdir ' . files::validatePath('packages/' . $packageId . '/files') . ' /S /Q')){
                    mklog(2,'Failed to remove old /files for package ' . $packageId);
                }
            }

            $info2 = self::getPackageVersionInfo($packageId,$downloadVersion);
            $info2['id_name'] = $info['id_name'];
            $info2['author'] = $info['author'];

            foreach(array('version','name') as $thing){
                if(!isset($info2[$thing])){
                    mklog(2,'Incomplete data for version download of ' . $packageId);
                    return false;
                }
            }

            $time = time::millistamp();
            $downloadFile = 'temp/pkgmgr/downloads/' . $packageId . '-' . $time . '.zip';
            mklog(1,'Downloading package ' . $packageId . ' version ' . $downloadVersion);
            $downloadTries = 0;
            retrydownload:
            if(downloader::downloadFile(self::$downloadSiteFiles . '/php-cli/packages/' . $packageId . '/' . $downloadVersion . '.zip',$downloadFile)){
                $downloadTries++;
                $zip = new ZipArchive;
                $result = $zip->open($downloadFile);
                if($result !== true){
                    if($downloadTries < 5){
                        goto retrydownload;
                    }
                    else{
                        mklog(2,'Failed to download a valid zip file for package ' . $packageId);
                    }
                }
                else{
                    $zip->close();
                }

                if($getDependencies){
                    mklog(0,'Downloading dependencies for package ' . $packageId);
                    if(isset($info2['dependencies'])){
                        if(is_array($info2['dependencies'])){
                            foreach($info2['dependencies'] as $dependency => $dependencyVersion){
                                if(!class_exists($dependency)){
                                    if(!self::downloadPackage($dependency)){
                                        mklog(2,'Failed to download dependency for ' . $packageId . ': ' . $dependency);
                                    }
                                }
                            }
                        }
                        else{
                            mklog(2,'Unknown dependencies format for package ' . $packageId);
                        }
                    }
                }

                files::ensureFolder('packages\\' . $packageId);

                retryzip:
                echo "Unpacking " . $packageId . "\n";
                $zip = new ZipArchive;
                $result = $zip->open($downloadFile);
                if($result === true){
                    $zip->extractTo('packages\\' . $packageId);
                    $zip->close();

                    if(!json::writeFile('packages\\' . $packageId . '\\information.json',$info2,true)){
                        mklog(2,'Unable to write information file for package ' . $packageId);
                    }

                    if(!unlink($downloadFile)){
                        mklog(0,'Unable to delete temporary download file ' . $downloadFile);
                    }

                    if($load){
                        echo "Loading " . $packageId . "\n";
                        return self::loadPackage($packageId,false);
                    }
                    else{
                        mklog(1,'Please restart PHP-CLI for the update to apply');
                        return true;
                    }
                }
                else{
                    if(!isset($zipfailed)){
                        $zipfailed = true;
                        mklog(0,'Trying again to open package zip file ' . $downloadFile);
                        goto retryzip;
                    }
                    else{
                        mklog(2,'Failed to open package zip file ' . $downloadFile);
                    }
                }
            }
            else{
                mklog(2,'Failed to download zip file for package ' . $packageId);
            }
        }
        return false;
    }
    public static function updatePackages():bool{
        $return = false;
        $glob = glob('packages/*');
        if(!is_array($glob)){
            mklog(2,'Unable to read packages folder');
        }
        foreach($glob as $dir){
            $packageId = files::getFileName($dir);
            if(self::doesPackageExist($packageId,false)){
                if(self::downloadPackage($packageId,false,false,false)){
                    $return = true;
                }
                else{
                    mklog(2,'Failed to update package ' . $packageId,false);
                }
            }
        }
        return $return;
    }
    public static function getPackageFileDependencies(string $packageId, bool $getLatestVersions=true):array|false{
        if(!self::validatePackageId($packageId)){
            return false;
        }

        $preloadedPackages = array("self","cli","cmd","commandline_list","data_types","downloader","extensions","files","json","time","timetest","txtrw","user_input");
        if(in_array($packageId,$preloadedPackages)){
            return [];
        }

        $file = 'packages/' . $packageId . '/main.php';
        if(!is_file($file)){
            return false;
        }
        $text = file_get_contents($file);
        if(!is_string($text)){
            mklog(2,'Unable to read package file ' . $file);
        }
        $offset = 0;
        $dependencies = array();
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
            if(!in_array($dependency,$preloadedPackages) && is_file('packages/' . $dependency . '/main.php')){
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
                    if($getLatestVersions){
                        if(!isset($dependencies[$dependency])){
                            mklog(1, 'Checking latest version of ' . $dependency);
                            $onlineInfo = self::getPackageInfo($dependency, true);
                            if(is_array($onlineInfo) && isset($onlineInfo['latest_version'])){
                                $dependencies[$dependency] = $onlineInfo['latest_version'];
                            }
                            else{
                                $dependencies[$dependency] = 1;
                                mklog(2, 'Failed to get latest version for ' . $dependency);
                            }
                        }
                    }
                    else{
                        if(!in_array($dependency, $dependencies)){
                            $dependencies[] = $dependency;
                        }
                    }
                }
            }
            $offset = $pos+2;
        }

        return $dependencies;
    }
    public static function getLoadedPackages():array{
        $return = [];
        foreach(self::$packages as $packageId => $packageInfo){
            $return[$packageId] = $packageInfo['version'];
        }
        return $return;
    }
    public static function getLatestPhpUrl():string|false{
        $data = json::readFile('https://windows.php.net/downloads/releases/releases.json',false);

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

        return 'https://windows.php.net/downloads/releases/' . $data['zip']['path'];
    }
}