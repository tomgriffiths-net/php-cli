<?php
date_default_timezone_set("Etc/GMT-1");

$startTime = floor(microtime(true)*1000);
mklog('general','Starting',false);//DO NOT EDIT THIS LINE - the string 'Starting' tells the mklog() function to begin logging and set variables

mklog('general','Loading resources',false);
require 'resources.php';
//Loaded: cli,cmd,commandline_list,data_types,downloader,extensions,files,json,time,txtrw,user_input

mklog('general','Reading start arguments');
require 'startup/arguments.php';
//arguments array set

mklog('general','Loading basic CLI interface',false);
require 'startup/cli_init.php';
//stdin and stdout file pointers set

//Update logs
mklog('general','Updating logs',false);//DO NOT EDIT THIS LINE - the string 'Updating logs' tells the mklog() function to update log files

mklog('general','Loading packages',false);
require 'package_manager/load.php';

require 'main.php';

function mklog(string $type, string $message, bool $verbose=true):void{
    //Assume log shouldnt be made
    $makelog = false;
    $verboseloggingsetting = false;
    //Check if the log is not verbose
    if($verbose !== true){
        //Make the log
        $makelog = true;
    }

    //Check if verbose-logging argument is set
    if(isset($GLOBALS['arguments']['verbose-logging'])){
        //If verbose-logging is true, make the log
        if($GLOBALS['arguments']['verbose-logging'] == true){
            $makelog = true;
            $verboseloggingsetting = true;
        }
    }
    //Check if verbose-logging file argument is set
    if(isset($GLOBALS['fileArguments']['verbose-logging'])){
        //If verbose-logging is true, make the log
        if($GLOBALS['fileArguments']['verbose-logging'] == true){
            $makelog = true;
            $verboseloggingsetting = true;
        }
    }
    
    //Check if log specifies an error
    if($type === 'error' || $type === 'warning'){
        //Allways make error log
        $makelog = true;
    }

    if($makelog){
        if(!is_dir('logs')){
            mkdir('logs',0777,true);
        }
        if($message === 'Starting' && !isset($GLOBALS['tempLogLinesArray'])){
            $GLOBALS['tempLogLinesArray'] = array();
            if(is_file('logs/latest.log')){
                unlink('logs/latest.log');
            }
        }
        if($message === 'Updating logs' && isset($GLOBALS['tempLogLinesArray'])){
            foreach($GLOBALS['tempLogLinesArray'] as $templine){
                $stream = fopen('logs/latest.log','a');
                fwrite($stream,$templine . "\n");
                fclose($stream);
                $stream = fopen('logs/log-' . date("Y-m") . '.txt','a');
                fwrite($stream,$templine . "\n");
                fclose($stream);
            }
            unset($GLOBALS['tempLogLinesArray']);
        }

        //Set miliseconds (Unix epoch)
        $milliseconds = floor(microtime(true)*1000);
        //Set date and time with microseconds
        $dateAndTime = date("Y-m-d_H:i:s:") . substr($milliseconds, 10);
        //Set line variable which contains the log
        $line = $dateAndTime . ": " . $type . ": " . $message;

        //Display log
        $colour = "normal";
        if($verbose !== true && $verboseloggingsetting){
            $colour = "light_green";
        }
        if($type === "warning"){$colour = "yellow";}
        if($type === "error"){$colour = "red";}
        if($colour !== "normal" && class_exists('cli_formatter')){
            echo cli_formatter::formatLine($line,$colour);
        }
        else{
            echo $line . "\n";
        }

        if(isset($GLOBALS['tempLogLinesArray'])){
            array_push($GLOBALS['tempLogLinesArray'],$line);
            //Check if type is an error
            if($type === 'error'){
                //Turn lowTempLogs array into log file string
                $tempLogs = implode("\n",$GLOBALS['tempLogLinesArray']);
                //Attempt to write logs to crash file
                if(file_put_contents('STARTUP-CRASH_' . date("Y-m-d_H:i:s:") . '.txt',$tempLogs) === false){
                    echo "Unable to create crash log, displaying data:\n\n\n";
                    sleep(1);
                    echo $tempLogs;
                    sleep(300);
                }
            }
        }
        else{
            $stream = fopen('logs/latest.log','a');
            fwrite($stream,$line . "\n");
            fclose($stream);
            $stream = fopen('logs/log-' . date("Y-m") . '.txt','a');
            fwrite($stream,$line . "\n");
            fclose($stream);
        }
    }
    if($type === 'error'){
        sleep(10);
        exit;
    }
}
function verboseLogging():bool{
    if(isset($GLOBALS['arguments']['verbose-logging'])){
        return $GLOBALS['arguments']['verbose-logging'];
    }
    return false;
}