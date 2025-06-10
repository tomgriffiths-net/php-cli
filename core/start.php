<?php
$startTime = floor(microtime(true)*1000);
mklog('general','Starting',false);

mklog('general','Loading resources',false);
require 'resources.php';
//Loaded: cli,cmd,commandline_list,data_types,downloader,extensions,files,json,time,txtrw,user_input

mklog('general','Reading start arguments');
require 'startup/arguments.php';
//arguments array set

mklog('general','Loading basic CLI interface',false);
require 'startup/cli_init.php';
//stdin and stdout file pointers set

mklog('general','Loading packages',false);
require 'package_manager/load.php';

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

    $type = max(min(3,$type),0);

    $trace = debug_backtrace();
    if(isset($trace[1]['class'])){
        $message = $trace[1]['class'] . ': ' . $message;
    }

    $makelog = false;
    $verboseloggingsetting = false;

    if($type){
        $makelog = true;
    }

    if(isset($GLOBALS['arguments']['verbose-logging'])){
        if($GLOBALS['arguments']['verbose-logging']){
            $makelog = true;
            $verboseloggingsetting = true;
        }
    }

    if(isset($GLOBALS['fileArguments']['verbose-logging'])){
        if($GLOBALS['fileArguments']['verbose-logging']){
            $makelog = true;
            $verboseloggingsetting = true;
        }
    }

    if($makelog){
        if(!is_dir('logs')){
            if(!mkdir('logs',0777,true)){
                echo "Error: Unable to create logs directory\n";
            }
        }

        //Full line
        $milliseconds = floor(microtime(true)*1000);
        $dateAndTime = date("Y-m-d_H:i:s:") . substr($milliseconds, -3);
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
        if(!fwrite($stream,$line . "\n")){
            echo "Error: Unable to write to latest.log\n";
        }
        if(!fclose($stream)){
            echo "Error: Unable to safely save latest.log\n";
        }

        $stream = fopen('logs\\log-' . date("Y-m") . '.txt','a');
        if(!$stream){
            echo "Error: Unable to open logs file\n";
        }
        if(!fwrite($stream,$line . "\n")){
            echo "Error: Unable to write to logs file\n";
        }
        if(!fclose($stream)){
            echo "Error: Unable to safely save logs file\n";
        }
    }
    if($type === 3){
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