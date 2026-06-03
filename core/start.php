<?php
if(php_sapi_name() !== "cli"){
    echo "This script can only be run from the command line.\n";
    exit;
}

if(PHP_VERSION_ID < 80000){
    echo "ERROR: PHP Version 8 or newer is required to run PHP-CLI\n";
    sleep(10);
    exit;
}
if(PHP_VERSION_ID < 80400){
    echo "Warning: PHP Version 8.4 or newer is recommended\n";
}

if(!in_array(PHP_OS_FAMILY, ['Windows','Linux'])){
    echo "This can only be run on Windows and some Linux distributions.\n";
    exit;
}

//Logs setup
if(!is_dir('logs')){
    if(!mkdir('logs',0777)){
        echo "Error: Unable to create logs directory\n";
    }
}
if(is_file('logs/latest.log')){
    if(!unlink('logs/latest.log')){
        echo "Warning: Unable to delete old latest.log\n";
    }
}

mklog(1,'Starting');

mklog(1,'Loading resources');
require_once 'resource2.php';

if(is_admin::check()){
    mklog(1,"Starting with administrator permissions");
}

mklog(1,'Reading start arguments');

if(!isset($fileArguments)){
    $fileArguments = [];
}
if(!is_array($fileArguments) || array_is_list($fileArguments)){
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
        'json-url-read-cache-timeout' => 5,
        'check-syntax' => false,
        'sleep-on-error' => 0
    ];

    $lineArguments = [];
    //Check if there is an even number of arguments as the first argument is the filename
    $argvCount = count($argv);
    if($argvCount %2 === 0){
        //Ignore arguments if even number of arguments (extra arguments number is arguments -1 as first argument is filename)
        mklog(2, 'Missmach in arguments provided via commandline, ignoring last item');

        //Pretend odd item does not exist
        $argvCount--;
    }
    
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
                $currentArgumentLogValue = $arguments[$defaultArgumentName] ? "true" : "false";
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

unset($fileArguments);

//////////

echo "\033]0;PHP-CLI: " . getcwd() . "\007";

mklog(1,'Loading packages');
require_once 'packages.php';

require_once 'main.php';

/**
 * Makes a log entry and displays it to the user.
 *
 * @param integer $type Severity from 0 to 3 (inclusive) where 0 is for verbose messages, 1 is general, 2 is warning, and 3 is error.
 * Error logs can pause the program for an amount of time in seconds if the sleep-on-error argument is above 0.
 * @param string $message The message to be logged.
 * @param string $formattedMessage Optionally a formatted version of the message that will be printed instead of the original message.
 * The original message will still be saved.
 * @return void If mklog has an issue it will shout at the user.
 */
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
        $stream = fopen('logs/latest.log','a');
        if(!$stream){
            echo "Error: Unable to open latest.log\n";
        }
        elseif(!fwrite($stream, $prefix . $message . "\n")){
            echo "Error: Unable to write to latest.log\n";
        }
        elseif(!fclose($stream)){
            echo "Error: Unable to save latest.log\n";
        }

        $stream = fopen('logs/log-' . date("Y-m") . '.txt','a');
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
        if(isset($cliFormatterExists) && $cliFormatterExists){
            cli_formatter::ding();
        }
        if($GLOBALS['arguments']['sleep-on-error'] > 0){
            sleep($GLOBALS['arguments']['sleep-on-error']);
        }
    }
}
/**
 * Gets weather verbose-logging is enabled.
 *
 * @return boolean Weather verbose-logging is enabled.
 */
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
/**
 * Returns a shell ready arguments string to preserve start arguments when restarting the program.
 *
 * @return string The shell escaped arguments.
 */
function argsString():string{
    global $argv;
    return implode(' ', array_map('escapeshellarg', array_slice($argv, 1)));
}

/**
 * Gets info on the environment php-cli is running in. (ClaudeAI and ChatGPT helped, mostly works)
 *
 * @return array An array of booleans with string keys; windows, linux, desktop, headless, remote_desktop, ssh, modern_terminal, compat_layer, tty, interactive, daemon_or_cron.
 */
function getEnvironment():array{
    $isWindows = PHP_OS_FAMILY === 'Windows';
    $isLinux   = PHP_OS_FAMILY === 'Linux';

    // Unified environment accessor
    $env = static fn(string $key): string|false => getenv($key);

    // --- Detect TTY / interactive console ---
    $hasTty = false;
    if(function_exists('stream_isatty')){
        $hasTty = @stream_isatty(STDIN) || @stream_isatty(STDOUT);
    }
    elseif(function_exists('posix_isatty')){
        $hasTty = @posix_isatty(STDIN) || @posix_isatty(STDOUT);
    }

    // --- Shared checks ---
    $isSSH = !empty($env('SSH_CLIENT')) || !empty($env('SSH_TTY'));

    // --- Defaults ---
    $isDesktop        = false;
    $isRemoteDesktop  = false;
    $isModernTerminal = false;
    $isCompatLayer    = false;

    if($isWindows){
        // CLIENTNAME is far more reliable than SESSIONNAME
        // Local sessions normally report "Console"
        // RDP sessions report remote client hostname
        $clientName = $env('CLIENTNAME');
        $isRemoteDesktop = !empty($clientName) && strtoupper($clientName) !== 'CONSOLE';

        // If we have a TTY and are not running through SSH,
        // assume an interactive desktop session
        $isDesktop = $hasTty && !$isSSH;

        // Modern terminal detection
        $isModernTerminal =
            !empty($env('WT_SESSION')) ||      // Windows Terminal
            !empty($env('WT_PROFILE_ID')) ||
            !empty($env('ConEmuPID')) ||       // ConEmu
            !empty($env('TERM_PROGRAM')) ||    // VSCode/etc
            !empty($env('ANSICON'));

        // Compatibility / subsystem layers
        $isCompatLayer =
            !empty($env('MSYSTEM')) ||         // MSYS2 / Git Bash
            !empty($env('CYGWIN'));
    
    }
    elseif($isLinux){

        $hasDisplay = !empty($env('DISPLAY')) || !empty($env('WAYLAND_DISPLAY'));

        $hasDesktopSession = !empty($env('DESKTOP_SESSION')) || !empty($env('XDG_CURRENT_DESKTOP')) || !empty($env('GNOME_DESKTOP_SESSION_ID')) || !empty($env('KDE_FULL_SESSION'));

        $isDesktop = $hasDisplay && $hasDesktopSession && !$isSSH;

        // Remote desktop detection
        $isRemoteDesktop = !empty($env('XRDP_SESSION')) || !empty($env('X2GO_SESSION'));

        // Modern terminal detection
        $term        = $env('TERM');
        $termProgram = $env('TERM_PROGRAM');

        $isModernTerminal = !empty($env('VTE_VERSION')) || !empty($termProgram) ||
            in_array(
                $term,
                [
                    'xterm-256color',
                    'screen-256color',
                    'tmux-256color',
                    'alacritty',
                    'wezterm',
                ],
                true
            );

        // Wine / WSL / compatibility layers
        $isCompatLayer = !empty($env('WINEPREFIX')) || file_exists('/proc/sys/fs/binfmt_misc/WSLInterop');
        
    }
    else{
        $isDesktop = $hasTty && !$isSSH;
    }

    return [

        // Platform
        'windows'         => $isWindows,
        'linux'           => $isLinux,

        // Environment type
        'desktop'         => $isDesktop,
        'headless'        => !$isDesktop,
        'remote_desktop'  => $isRemoteDesktop,
        'ssh'             => $isSSH,
        'modern_terminal' => $isModernTerminal,
        'compat_layer'    => $isCompatLayer,

        // TTY / interactivity
        'tty'             => $hasTty,
        'interactive'     => $hasTty && !$isSSH,
        'daemon_or_cron'  => !$hasTty && !$isSSH,
    ];
}