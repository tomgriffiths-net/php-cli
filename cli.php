<?php
//Default startup arguments, uncommnt them to set them.

//$fileArguments['verbose-logging'] = true;
//$fileArguments['use-file-as-input'] = "<Path to semi-existant single-line text file>";
//$fileArguments['no-loop'] = true;
//$fileArguments['command'] = "<Your command here>";


//Check if program is running under cli
if(php_sapi_name() !== "cli"){
    //Exit if not running under cli
    echo "This script can only be run from the command line.\n";
    exit;
}

//Ensure correct working directory
chdir(dirname(__FILE__));

//Start the program
require 'core/start.php';////