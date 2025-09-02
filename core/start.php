<?php
//Required checks
if($_SERVER['OS'] !== "Windows_NT"){
    echo "ERROR: This program can only run on modern Windows\n";
    exit;
}
if(PHP_VERSION_ID < 80000){
    echo "ERROR: PHP Version 8 or newer is required to run PHP-CLI\n";
    exit;
}
if(PHP_VERSION_ID < 80301){
    echo "Warning: PHP Version 8.3.1 or newer is recommended\n";
}

//Logs setup
if(!is_dir('logs')){
    if(!mkdir('logs',0777)){
        echo "Error: Unable to create logs directory\n";
    }
}
if(is_file('logs\\latest.log')){
    if(!unlink('logs\\latest.log')){
        echo "Warning: Unable to delete old latest.log\n";
    }
}

mklog(1,'Starting');

mklog(1,'Loading resources');
require 'resources.php';
//Loaded: cli_formatter,cmd,commandline_list,data_types,downloader,extensions,files,json,time,txtrw,user_input

mklog(0,'Reading start arguments');
require 'startup/arguments.php';
//arguments array set

mklog(1,'Loading basic CLI interface');
require 'startup/cli_init.php';
//stdin and stdout file pointers set

mklog(1,'Loading packages');
require 'package_manager/load.php';
//packages loaded

//start cli input
require 'main.php';

function mklog(int|string $type, string $message, bool $verbose=true):void{
    //Convert old to new format if detected
    if(!is_int($type)){
        $type = substr(strtolower($type), 0, 1);
        $type = ["g"=>1,"w"=>2,"e"=>3][$type];
        if($verbose){
            $type = 0;
        }
    }

    //

    $type = min(max($type,0),3);

    $trace = debug_backtrace();
    if(isset($trace[1]['class'])){
        $message = $trace[1]['class'] . ': ' . $message;
    }

    $verboseloggingsetting = verboseLogging();

    if($type || $verboseloggingsetting){
        //Full line
        $dateAndTime = date("Y-m-d_H:i:s:") . substr(floor(microtime(true)*1000), -3);
        $line = $dateAndTime . ": " . ["Verbose","General","Warning","Error"][$type] . ": " . $message;

        //Display log
        $colour = "normal";
        if($type && $verboseloggingsetting){
            $colour = "light_green";
        }
        if($type === 2){$colour = "yellow";}
        if($type === 3){$colour = "red";}
        if($colour !== "normal" && class_exists('cli_formatter')){
            echo cli_formatter::formatLine($line,$colour);
        }
        else{
            echo $line . "\n";
        }

        //Write files
        $stream = fopen('logs\\latest.log','a');
        if(!$stream){
            echo "Error: Unable to open latest.log\n";
        }
        elseif(!fwrite($stream,$line . "\n")){
            echo "Error: Unable to write to latest.log\n";
        }
        elseif(!fclose($stream)){
            echo "Error: Unable to save latest.log\n";
        }

        $stream = fopen('logs\\log-' . date("Y-m") . '.txt','a');
        if(!$stream){
            echo "Error: Unable to open logs file\n";
        }
        elseif(!fwrite($stream,$line . "\n")){
            echo "Error: Unable to write to logs file\n";
        }
        elseif(!fclose($stream)){
            echo "Error: Unable to save logs file\n";
        }
    }
    if($type === 3){
        cli_formatter::ding();
        sleep(2);
    }
}
function verboseLogging():bool{
    if(isset($GLOBALS['arguments']['verbose-logging'])){
        if($GLOBALS['arguments']['verbose-logging']){
            return true;
        }
    }
    if(isset($GLOBALS['fileArguments']['verbose-logging'])){
        if($GLOBALS['fileArguments']['verbose-logging']){
            return true;
        }
    }
    return false;
}