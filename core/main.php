<?php
mklog(1,'Loading packages');
require_once 'packages.php';

mklog(1,'Took ' . round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3) . ' seconds to start');

cli::start();

/**
 * The main program and command line.
 */
class cli{
    private static $started = false;
    private static $aliases = [];
    /**
     * @internal
     */
    public static function command(string $line):void{
        $lines = explode(" ", $line);
        if($lines[0] === "new"){
            cmd::newWindow(cmd::startScript() . " " . substr($line,4));
        }
        elseif($lines[0] === "reload"){
            $cmd = cmd::startScript() . " " . argsString();
            if(getEnvironment()['desktop']){
                cmd::newWindow($cmd);
                exit;
            }
            else{
                exit(5);//exit code 5 signals the start script to restart php
            }
        }
        elseif($lines[0] === "clear"){
            cli_formatter::clear();
        }
        else{
            echo "Commands: new [args], reload, clear\n";
        }
    }

    /**
     * @internal
     */
    public static function start(){
        if(self::$started){
            return;
        }
        self::$started = true;

        while(true){
            foreach(self::getCommands() as $command){
                self::run($command);
            }
            self::after();
        }
    }
    /**
     * Runs a command.
     *
     * @param string $line The command to run.
     * @param boolean $captureOutput Weather to return the recorded echo's or not.
     * @return boolean|string If captureOutput is false, a boolean indicating succcess will be returned, otherwise a string will be returned.
     */
    public static function run(string $line, bool $captureOutput=false):bool|string{
        $return = false;
        if($line === "exit"){
            mklog(0, 'Exiting');
            exit;
        }

        if(strpos($line," ") !== false){
            $spacePos = strpos($line," ");
            $baseCommand = substr($line,0,$spacePos);
            $line = substr($line,$spacePos+1);
        }
        else{
            $baseCommand = $line;
            $line = "";
        }
        $baseCommand = strtolower($baseCommand);

        if(isset(self::$aliases[$baseCommand])){
            return self::run(self::$aliases[$baseCommand] . " " . $line, $captureOutput);
        }

        if($captureOutput){
            mklog(0, 'Breifly capturing output');
            ob_start();
        }

        if(class_exists($baseCommand)){
            if(method_exists($baseCommand,'command')){
                $return = true;
                try{
                    $baseCommand::command($line);
                }
                catch(Throwable $throwable){
                    mklog(3,"Something went wrong while trying to run: " . $baseCommand . " " . $line . " (" . substr($throwable,0,strpos($throwable,"\n")) . ")");
                    $return = false;
                }
            }
            else{
                echo "The package " . $baseCommand . " does not have a command function.\n";
            }
        }
        else{
            echo "Unknown package or command: " . $baseCommand . "\n";
        }

        if($captureOutput){
            return ob_get_clean();
        }
        
        return $return;
    }
    /**
     * Registers a command alias, this allows you to map a command to a longer one.
     *
     * @param string $alias The nickname for the command.
     * @param string $command The command the alias gets replaced with.
     * @return boolean Indicates success.
     */
    public static function registerAlias(string $alias, string $command):bool{
        if(class_exists($alias) || isset(self::$aliases[$alias]) || $alias === "exit"){
            return false;
        }

        if(verboseLogging()){
            $class = false;
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            if(isset($trace[1]['class'])){
                $class = $trace[1]['class'];
            }

            mklog(0, ($class ? $class . ' r' : 'R') . 'egistered the alias "' . $alias . '" for the command "' . $command . '"');
        }

        self::$aliases[$alias] = $command;
        return true;
    }

    /**
     * Returns the version number of PHP-CLI.
     *
     * @return integer The current PHP-CLI version number.
     */
    public static function version():int{
        return 107;
    }
    /**
     * Gets general info about the PHP-CLI install and some system information.
     *
     * @return array See readme.
     */
    public static function info():array{
        return [
            'version' => self::version(),
            'startTime' => $_SERVER['REQUEST_TIME_FLOAT'],
            'pcName' => gethostname(),
            'cpuThreads' => (PHP_OS_FAMILY === 'Linux' ? (int) shell_exec('nproc') : $_SERVER['NUMBER_OF_PROCESSORS']),
            'phpVersionNumeric' => PHP_VERSION_ID,
            'phpVersion' => phpversion(),
            'osType' => PHP_OS_FAMILY,
            'osVersion' => php_uname('r'),
            'osBuild' => php_uname('v')
        ];
    }
    /**
     * Parses a command string into arguments, options and parameters.
     * Inputted "words" can be quoted with double-quotes to stop spaces making it 2 words.
     * Arguments are strings on their own, options are preceded with a "-" and are added to the options list if they exist,
     * parameters are preceded with "--" and then take watever was behind them as the value, this is added to the params array and is keyed with the parameter name and has the parameter string value.
     *
     * @param string $line The line containing words.
     * @return array The args, options, and params arrays.
     */
    public static function parseLine(string $line):array{
        $words = str_getcsv($line, ' ', '"', '\\');
    
        $return = [
            "args" => [],
            "options" => [],
            "params" => []
        ];

        $param = null;
        foreach($words as $word){
            $word = trim($word);

            if($param){
                $return["params"][$param] = $word;
                $param = null;
                continue;
            }

            if(substr($word, 0, 2) === "--" && strlen($word) > 2){
                $param = strtolower(substr($word, 2));
                continue;
            }
            if(substr($word, 0, 1) === "-"  && strlen($word) > 1){
                $return["options"][] = strtolower(substr($word, 1));
                continue;
            }

            $return["args"][] = $word;
        }

        return $return;
    }

    private static function getCommands():array{
        global $arguments;

        if(is_string($arguments['command'])){
            mklog(0, 'Running argument command');
            $line = $arguments['command'];
        }
        elseif(is_string($arguments['use-file-as-input'])){
            if(!is_file($arguments['use-file-as-input'])){
                return [];
            }

            mklog(0, 'Running command from the file ' . $arguments['use-file-as-input']);

            $line = file_get_contents($arguments['use-file-as-input']);

            if(!is_string($line) || empty($line)){
                mklog(2,'Failed to read command from ' . $arguments['use-file-as-input']);
                return [];
            }
        }
        else{
            $line = user_input::await();
        }

        return explode(" && ", $line);
    }
    private static function after(){
        global $arguments;

        if($arguments['no-loop'] === true){
            exit;
        }

        if(is_string($arguments['command'])){
            $arguments['command'] = false;
        }

        if(is_string($arguments['use-file-as-input'])){
            if(is_file($arguments['use-file-as-input'])){
                if(!unlink($arguments['use-file-as-input'])){
                    mklog(3,'Failed to delete executed command file ' . $arguments['use-file-as-input']);
                    $arguments['use-file-as-input'] = false;
                }
            }
            sleep($arguments['file-as-input-delay']);
        }
    }
}