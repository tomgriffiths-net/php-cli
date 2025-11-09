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
$arguments = (function(){
    global $argv;

    //Set default arguments
    $defaultArguments = [
        'verbose-logging' => false,
        'use-file-as-input' => false,
        'file-as-input-delay' => 10,
        'no-loop' => false,
        'command' => false
    ];

    $lineArguments = [];
    //Check if there is an even number of arguments as the first argument is the filename
    $argvCount = count($argv);
    if($argvCount %2 === 0){
        //Ignore arguments if even number of arguments (extra arguments number is arguments -1 as first argument is filename)
        mklog(2,'Missmach in arguments provided via commandline, ignoring all');
    }
    else{
        //Go through each provided command line argument
        for($i = 1; $i < $argvCount; $i++){
            //Pair up every other argument to the argument after it e.g. <arg1> <arg1value> <arg2> <arg2value>
            $lineArguments[$argv[$i]] = $argv[$i+1];
            $i++;
        }
        //Go through every command line argument
        foreach($lineArguments as $lineArgumentName => $lineArgumentValue){
            $lineArguments[$lineArgumentName] = data_types::convert_string($lineArgumentValue);
        }
    }

    if(isset($fileArguments)){
        if(!is_array($fileArguments)){
            mklog(2,'Unknown fileArguments configuration, ignoring all');
            $fileArguments = [];
        }
    }
    else{
        $fileArguments = [];
    }

    //Now there are three argument arrays that will be overritten by the array after it: defaultArguments, fileArguments, lineArguments

    $arguments = [];
    //Go through every known argument
    foreach($defaultArguments as $defaultArgumentName => $defaultArgumentValue){
        //Initially set argument value to default value
        $arguments[$defaultArgumentName] = $defaultArgumentValue;
        //Check if file argument for current argument is set
        if(isset($fileArguments[$defaultArgumentName])){
            //Overwrite argument value
            $arguments[$defaultArgumentName] = $fileArguments[$defaultArgumentName];
        }
        //Check if command line argument for current argument is set
        if(isset($lineArguments[$defaultArgumentName])){
            //Overwrite argument value
            $arguments[$defaultArgumentName] = $lineArguments[$defaultArgumentName];
        }
        
        //Log stuff
        if(is_bool($arguments[$defaultArgumentName])){
            $currentArgumentLogValue = data_types::boolean_to_string($arguments[$defaultArgumentName]);
        }
        else{
            $currentArgumentLogValue = $arguments[$defaultArgumentName];
        }
        mklog(0,'Argument ' . $defaultArgumentName . ' is set to ' . $currentArgumentLogValue . ' with data type of ' . gettype($arguments[$defaultArgumentName]));
        
    }

    return $arguments;
})();

//////////

mklog(1,'Loading basic CLI interface');
mklog(0,'Setting window title');
exec('title PHP-CLI: ' . getcwd());

mklog(0,'Loading stdin and stdout');
$stdout = fopen("php://stdout","w");
if(!$stdout){
    mklog(3,'Unable to load stdout',false);
}

$stdin = fopen("php://stdin","r");
if(!$stdin){
    mklog(3,'Unable to load stdin',false);
}

//////////

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