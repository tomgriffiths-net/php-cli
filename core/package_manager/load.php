<?php
pkgmgr::init();

$downloadSite = 'https://www.tomgriffiths.net';
$downloadSiteFiles = 'https://files.tomgriffiths.net';

class pkgmgr{
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
            foreach($GLOBALS['packages'] as $packageId => $packageInfo){
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

        downloader::init();
        extensions::init();

        if(!is_dir('packages')){
            mkdir('packages',0777,true);
        }
        
        $GLOBALS['packageCount'] = 0;
        $GLOBALS['packageInitCount'] = 0;
        foreach(glob('packages/*') as $dir){
            $package = substr($dir,strripos($dir,"/")+1);
            if(!class_exists($package)){
                if(self::loadPackage($package) !== true){
                    mklog('warning','Unable to load package ' . $package,false);
                }
            }
        }
        
        mklog('general','Loaded ' . $GLOBALS['packageCount'] . ' packages, ' . $GLOBALS['packageInitCount'] . ' initialized',false);
    }
    public static function loadPackage(string $package, int|bool $version = false):bool{
        if(self::validatePackageId($package)){
            $preloadedPackages = array("self","cli","cmd","commandline_list","data_types","downloader","extensions","files","json","time","timetest","txtrw","user_input");
            if(in_array($package,$preloadedPackages)){
                return true;
            }

            if(!class_exists($package)){
                $info = self::getPackageInfo($package,false);
                if(is_int($version)){
                    if($info['version'] > $version){
                        mklog('warning','Unable to load package ' . $package . ' version ' . $version . ' because version ' . $info['version'] . ' is installed',false);
                        return false;
                    }
                }
                if(is_array($info)){
                    foreach($info['dependencies'] as $dependencyId => $dependencyVersion){
                        if(!in_array($dependencyId,$preloadedPackages)){
                            $dependencyInfo = self::getPackageInfo($dependencyId,false);
                            if(is_array($dependencyInfo)){
                                if($dependencyInfo['version'] < $dependencyVersion){
                                    mklog('warning','Unable to load package ' . $package . ' as one of its dependencies (' . $dependencyId . ') has an incorrect version of ' . $dependencyVersion,false);
                                    return false;
                                }
                            }
                            else{
                                mklog('warning','Unable to load package ' . $package . ' as one of its dependencies (' . $dependencyId . ') does not exist',false);
                                return false;
                            }
                        }
                    }
                    foreach($info['dependencies'] as $dependencyId => $dependencyVersion){
                        if(!class_exists($dependencyId)){
                            if(self::loadPackage($dependencyId) !== true){
                                return false;
                            }
                        }
                    }

                    mklog('general','Loading package ' . $package);

                    if(is_file($info['dir'] . "\\main.php")){
                        include_once $info['dir'] . "\\main.php";
                        $GLOBALS['packageCount']++;
                        if(method_exists($info['id_name'],"init")){
                            mklog('general','Running init for package ' . $info['name'],false);
                            $info['id_name']::init();
                            $GLOBALS['packageInitCount']++;
                        }
                        $GLOBALS['packages'][$package] = $info;
                        return true;
                    }
                }
                else{
                    mklog('warning','Package ' . $package . ' does not exist',false);
                }
            }
            else{
                mklog('general','Package ' . $package . ' is allready loaded',false);
            }
        }
        return false;
    }
    public static function validatePackageId(string $packageId):bool{
        if(is_string($packageId)){
            if(preg_match("/^[a-zA-Z0-9_]+$/",$packageId) === 1){
                return true;
            }
        }
        return false;
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
        global $downloadSite;
        if(self::validatePackageId($packageId)){
            if($online === true){
                $result = json::readFile($downloadSite . '/php-cli/api/?function=getPackageInfo&packageId=' . $packageId,false);
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
        global $downloadSite;
        if(self::validatePackageId($packageId)){
            $result = json::readFile($downloadSite . '/php-cli/api/?function=getPackageVersionInfo&packageId=' . $packageId . '&version=' . $version,false);
            if(isset($result['success'])){
                if($result['success'] === true){
                    return $result['data'];
                }
            }
        }
        return false;
    }
    public static function downloadPackage(string $packageId, int|bool $version = false, bool $getDependencies = true, bool $load = true):bool{
        global $downloadSiteFiles;
        if(self::validatePackageId($packageId)){

            $info = self::getPackageInfo($packageId,true);

            if(!is_array($info)){
                mklog('warning','Package ' . $packageId . ' does not exist',false);
                return false;
            }

            foreach(array('id_name','author','versions','name','latest_version') as $thing){
                if(!isset($info[$thing])){
                    mklog('warning','Incomplete data for download of ' . $packageId,false);
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
                if(isset($localInfo['version'])){
                    if($localInfo['version'] == $downloadVersion){
                        mklog('general','Version of package ' . $packageId . ' already matches',false);
                        return true;
                    }
                }
            }

            if(is_file('packages/' . $packageId . '/.noupdate')){
                mklog('general','Package ' . $packageId . ' is marked for not updating (.noupdate file found)',false);
                return true;
            }

            if(is_dir('packages/' . $packageId . '/files')){
                cmd::run('rmdir ' . files::validatePath('packages/' . $packageId . '/files') . ' /S /Q');
            }

            $info2 = self::getPackageVersionInfo($packageId,$downloadVersion);
            $info2['id_name'] = $info['id_name'];
            $info2['author'] = $info['author'];

            foreach(array('version','name') as $thing){
                if(!isset($info2[$thing])){
                    mklog('warning','Incomplete data (version specific info) for download of ' . $packageId,false);
                    return false;
                }
            }

            $time = time::millistamp();
            $downloadFile = 'temp/pkgmgr/downloads/' . $packageId . '-' . $time . '.zip';
            mklog('general','Downloading package ' . $packageId . ' version ' . $downloadVersion,false);
            $downloadTries = 0;
            retrydownload:
            if(downloader::downloadFile($downloadSiteFiles . '/php-cli/packages/' . $packageId . '/' . $downloadVersion . '.zip',$downloadFile)){
                $downloadTries++;
                $zip = new ZipArchive;
                $result = $zip->open($downloadFile);
                if($result !== true){
                    if($downloadTries < 5){
                        goto retrydownload;
                    }
                    else{
                        mklog('warning','Failed to download a valid zip file',false);
                    }
                }
                else{
                    $zip->close();
                }

                if($getDependencies){
                    mklog('general','Downloading package dependencies');
                    if(isset($info2['dependencies'])){
                        if(is_array($info2['dependencies'])){
                            foreach($info2['dependencies'] as $dependecy => $dependencyVersion){
                                if(!class_exists($dependecy)){
                                    if(!self::downloadPackage($dependecy)){
                                        mklog('warning','Failed to download dependency: ' . $dependecy);
                                    }
                                }
                            }
                        }
                        else{
                            mklog('warning','Unknown dependencies format',false);
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

                    json::writeFile('packages\\' . $packageId . '\\information.json',$info2,true);

                    unlink($downloadFile);

                    if($load){
                        echo "Loading " . $packageId . "\n";
                        return self::loadPackage($packageId,false);
                    }
                    else{
                        mklog('general','Please restart for the update to apply');
                        return true;
                    }
                }
                else{
                    if(!isset($zipfailed)){
                        $zipfailed = true;
                        goto retryzip;
                    }
                    else{
                        mklog('warning','Failed to open package zip file',false);
                    }
                }
            }
            else{
                mklog('warning','Failed to download file',false);
            }
        }
        return false;
    }
    public static function updatePackages():bool{
        $return = false;
        foreach(glob('packages/*') as $dir){
            $packageId = files::getFileName($dir);
            if(self::doesPackageExist($packageId,false)){
                if(self::downloadPackage($packageId,false,false,false)){
                    $return = true;
                }
                else{
                    mklog('warning','Failed to update package ' . $packageId,false);
                }
            }
        }
        return $return;
    }
    public static function getPackageFileDependencies(string $packageId):array|false{
        if(!self::validatePackageId($packageId)){
            return false;
        }

        $preloadedPackages = array("self","cli","cmd","commandline_list","data_types","downloader","extensions","files","json","time","timetest","txtrw","user_input");
        if(in_array($packageId,$preloadedPackages)){
            return array();
        }

        $file = 'packages/' . $packageId . '/main.php';
        if(!is_file($file)){
            return false;
        }
        $text = file_get_contents($file);
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

                    $dependencies[$dependency] = 1;
                }
                
            }
            $offset = $pos+2;
        }

        return $dependencies;
    }
    public static function getLoadedPackages():array{
        $return = array();
        foreach($GLOBALS['packages'] as $packageId => $packageInfo){
            $return[$packageId] = $packageInfo['version'];
        }
        return $return;
    }
    public static function getLatestPhpUrl():string|false{
        $data = json::readFile('https://windows.php.net/downloads/releases/releases.json',false);
        
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