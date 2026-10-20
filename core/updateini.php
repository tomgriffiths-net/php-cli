<?php
$settings = [
    'max_execution_time' => '3600',
    'max_input_time' => '3600',
    'memory_limit' => '256M',
    'error_reporting' => (string) E_ALL,
    'display_errors' => '1',
    'display_startup_errors' => '1',
    'upload_max_filesize' => '8M'
];

$extensions = [
    'openssl', //openssl at beginning so later extensions can be installed on windows
    'readline',
    'curl',
    'zip',
    'sockets'
];

if(PHP_OS_FAMILY === 'Linux'){
    $extensions[] = 'posix';
}

/////

if(!in_array(PHP_OS_FAMILY, ['Windows','Linux'])){
    echo "This can only be run on Windows and some Linux distributions.\n";
    exit(1);
}

require_once 'resource1.php';

$iniSettingsSet = inimgmt::doesSettingsMatch($settings);
$extensionsLoaded = extensions::areLoaded($extensions);

$currentCaPath = ini_get("curl.cainfo");
$caInstalled = !empty($currentCaPath) && is_file($currentCaPath);

if(!$iniSettingsSet || !$extensionsLoaded || !$caInstalled){

    $iniPath = inimgmt::iniPath();
    if(!is_string($iniPath)){
        mklog0(3, "Failed to get the path of the loaded php.ini file");
        exit(1);
    }

    if(!is_writable($iniPath)){
        if(is_admin::check()){
            mklog0(3, "The loaded php.ini file is not editable even with admin/root privileges");
            exit(1);
        }

        mklog0(2, "The loaded php.ini file is not editable, retrying as admin/root");
        sleep(1);

        if(PHP_OS_FAMILY === 'Windows'){
            $exitCode = windowscmd::runElevated("php", ["core/updateini.php"]);
        }
        else{
            $exitCode = linuxcmd::sudo('php core/updateini.php');
        }

        if($exitCode){
            mklog0(3, "Failed to run as admin/root");
            exit(1);
        }

        exit;
    }

    if(!$iniSettingsSet){
        if(!inimgmt::setIniSettings($settings)){
            mklog0(3, "Failed to set recommended php.ini settings");
            exit(1);
        }
    }

    if(!$extensionsLoaded){
        foreach($extensions as $extension){
            if(!extensions::setEnabled($extension, true, true)){
                mklog0(3, "Failed to enable extension $extension");
                exit(1);
            }
        }
    }

    if(!$caInstalled){
        $caBundlePath = dirname($iniPath) . '/cacert.pem';
        if(!file_exists($caBundlePath)){
            echo "Downloading curl CA bundle...\n";
            if(!downloadCaBundle($caBundlePath)){
                mklog0(2, "Could not download a CA bundle. Please install manually from https://curl.se/ca/cacert.pem to $caBundlePath");
                exit(1);
            }
        }
        if(!inimgmt::setIniSettings(["curl.cainfo" => $caBundlePath])){
            mklog0(2, "Failed to update ini file with curl ca bundle");
            exit(1);
        }
    }
}

echo "All settings ok.\n";