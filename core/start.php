<?php
//Required checks
if($_SERVER['OS'] !== "Windows_NT"){
    echo "ERROR: This program can only run on modern Windows\n";
    sleep(10);
    exit;
}
if(PHP_VERSION_ID < 80000){
    echo "ERROR: PHP Version 8 or newer is required to run PHP-CLI\n";
    sleep(10);
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

if(!isset($fileArguments)){
    $fileArguments = [];
}
if(!is_array($fileArguments)){
    mklog(2,'Unknown fileArguments configuration, ignoring all');
    $fileArguments = [];
}

$arguments = (function(){
    global $argv;
    global $fileArguments;

    //Set default arguments
    $defaultArguments = [
        'verbose-logging' => false,
        'use-file-as-input' => false,
        'file-as-input-delay' => 10,
        'no-loop' => false,
        'command' => false,
        'json-read-cache-timeout' => 1,
        'json-url-read-cache-timeout' => 5
    ];

    $lineArguments = [];
    //Check if there is an even number of arguments as the first argument is the filename
    $argvCount = count($argv);
    if($argvCount %2 === 0){
        //Ignore arguments if even number of arguments (extra arguments number is arguments -1 as first argument is filename)
        mklog(2, 'Missmach in arguments provided via commandline, ignoring all');
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
        if(verboseLogging()){
            if(is_bool($arguments[$defaultArgumentName])){
                $currentArgumentLogValue = data_types::boolean_to_string($arguments[$defaultArgumentName]);
            }
            else{
                $currentArgumentLogValue = $arguments[$defaultArgumentName];
            }

            $type = gettype($arguments[$defaultArgumentName]);

            $colors = ["boolean"=>"light_blue", "integer"=>"light_green", "double"=>"light_green", "string"=>"light_red"];
            $color = false;
            if(isset($colors[$type])){
                $color = $colors[$type];
            }

            $defaultArgumentNameFormatted = cli_formatter::formatLine($defaultArgumentName, "white", false, false);
            $currentArgumentLogValueFormatted = cli_formatter::formatLine($currentArgumentLogValue, $color, false, false);
            $typeFormatted = cli_formatter::formatLine($type, "white", false, false);

            mklog(
                0,
                'Argument ' . $defaultArgumentName . ' is set to ' . $currentArgumentLogValue . ' with data type of ' . $type,
                $color ? 'Argument ' . $defaultArgumentNameFormatted . ' is set to ' . $currentArgumentLogValueFormatted . ' with data type of ' . $typeFormatted : ''
            );
        }
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
    mklog(2,'Unable to load stdout');
}

$stdin = fopen("php://stdin","r");
if(!$stdin){
    mklog(3,'Unable to load stdin');
}

//////////

require 'main.php';

function mklog(int|string $type, string $message, string|bool $formattedMessage=''):void{
    //Convert old to new format if detected
    if(!is_int($type)){

        mklog(0, 'The following log used an outdated version of mklog()');

        $type = substr(strtolower($type), 0, 1);

        if($type === "e"){$type = 3;}
        elseif($type === "w"){$type = 2;}
        else{$type = 1;}

        if($formattedMessage === true){
            $type = 0;
        }
    }

    if(!is_string($formattedMessage)){
        $formattedMessage = '';
    }

    //

    $type = min(max($type,0),3);

    $verboseloggingsetting = verboseLogging();

    if($type || $verboseloggingsetting){

        $prefix = date("Y-m-d_H:i:s:") . substr(floor(microtime(true)*1000), -3) . ": " . ["Verbose","General","Warning","Error"][$type] . ": ";

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        if(isset($trace[1]['class'])){
            $prefix .= $trace[1]['class'] . ': ';
        }

        $cliFormatterExists = class_exists('cli_formatter');

        //Display log
        $colour = "normal";
        if($type && $verboseloggingsetting){
            $colour = "light_green";
        }
        if($type === 2){$colour = "yellow";}
        if($type === 3){$colour = "red";}

        if(!empty($formattedMessage)){
            echo $prefix . $formattedMessage . "\n";
        }
        elseif($colour !== "normal" && $cliFormatterExists){
            echo cli_formatter::formatLine($prefix . $message, $colour);
        }
        else{
            echo $prefix . $message . "\n";
        }

        //Write files
        $stream = fopen('logs\\latest.log','a');
        if(!$stream){
            echo "Error: Unable to open latest.log\n";
        }
        elseif(!fwrite($stream, $prefix . $message . "\n")){
            echo "Error: Unable to write to latest.log\n";
        }
        elseif(!fclose($stream)){
            echo "Error: Unable to save latest.log\n";
        }

        $stream = fopen('logs\\log-' . date("Y-m") . '.txt','a');
        if(!$stream){
            echo "Error: Unable to open logs file\n";
        }
        elseif(!fwrite($stream, $prefix . $message . "\n")){
            echo "Error: Unable to write to logs file\n";
        }
        elseif(!fclose($stream)){
            echo "Error: Unable to save logs file\n";
        }
    }

    if($type === 3){
        if($cliFormatterExists){
            cli_formatter::ding();
        }
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