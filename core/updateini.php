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
    'mysqli',
    'sockets',
    'zip'
];

/////

if(!in_array(PHP_OS_FAMILY, ['Windows','Linux'])){
    echo "This can only be run on Windows and some Linux distributions.\n";
    exit(1);
}

require_once 'resource1.php';

if(isset($argv[1]) && $argv[1] === "setini"){
    if(!inimgmt::setIniSettings($settings)){
        echo "Error: Failed to set ini settings\n";
        exit(1);
    }
    echo "Ini settings set\n";
    exit(0);
}

if(!inimgmt::doesSettingsMatch($settings)){
    if(PHP_OS_FAMILY === 'Linux'){
        echo "Running ini setting changes under sudo\n";
        if(linuxcmd::sudo('php core/updateini.php setini') !== 0){
            exit(1);
        }
    }
    else{
        if(!inimgmt::setIniSettings($settings)){
            echo "Error: Failed to set recommended ini settings\n";
            exit(1);
        }
    }
}

if(!extensions::areLoaded($extensions)){
    foreach($extensions as $extension){
        if(!extensions::setEnabled($extension, true, true)){
            echo "Error: Failed to enable extension " . $extension . "\n";
            exit(1);
        }
    }
}

echo "All settings ok.\n";