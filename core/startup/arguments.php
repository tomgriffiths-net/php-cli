<?php
//Set default arguments
$defaultArguments = array();
$defaultArguments['first-time-startup'] = false;
$defaultArguments['verbose-logging'] = false;
$defaultArguments['use-file-as-input'] = false;
$defaultArguments['file-as-input-delay'] = 10;
$defaultArguments['no-loop'] = false;
$defaultArguments['command'] = false;

$lineArguments = array();
//Check if there is an even number of arguments as the first argument is the filename
$argvCount = count($argv);
if($argvCount %2 === 0){
    //Ignore arguments if even number of arguments (extra arguments number is arguments -1 as first argument is filename)
    mklog('warning','Missmach in arguments provided via commandline, ignoring all command line arguments (W001)',false);
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
        mklog('warning','Unknown fileArguments configuration, ignoring all (W002)',false);
        $fileArguments = array();
    }
}
else{
    $fileArguments = array();
}

//Now there are three argument arrays that will be overritten by the array after it: defaultArguments, fileArguments, lineArguments

$arguments = array();
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
    
    //////////////////////////////////////////
    //Code for verbose logging argument values
    //////////////////////////////////////////
    if(is_bool($arguments[$defaultArgumentName])){
        $currentArgumentLogValue = data_types::boolean_to_string($arguments[$defaultArgumentName]);
    }
    else{
        $currentArgumentLogValue = $arguments[$defaultArgumentName];
    }
    mklog('general','Argument ' . $defaultArgumentName . ' is set to ' . $currentArgumentLogValue . ' with data type of ' . gettype($arguments[$defaultArgumentName]));
    //////////////////////////////////////////
}