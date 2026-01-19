<?php
mklog(1,'Loading packages');
require 'packages.php';
//packages loaded

mklog(1,'Took ' . round(microtime(true) - cli::info()['startTime'], 3) . ' seconds to start');

cli::start();

class cli{
    private static $started = false;
    private static $aliases = [];

    public static function command($line):void{
        $lines = explode(" ", $line);
        if($lines[0] === "new"){
            cmd::newWindow("php\php cli.php " . substr($line,4));
        }
        elseif($lines[0] === "reload"){
            cmd::newWindow("php\php cli.php");
            exit;
        }
        elseif($lines[0] === "clear"){
            cli_formatter::clear();
        }
        else{
            echo "Commands: new [args], reload, clear\n";
        }
    }

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

    public static function info():array{
        return [
            'version' => 102,
            'startTime' => $_SERVER['REQUEST_TIME_FLOAT'],
            'pcName' => gethostname(),
            'pcDrive' => $_SERVER['SystemDrive'],
            'cpuThreads' => $_SERVER['NUMBER_OF_PROCESSORS'],
            'cpuType' => $_SERVER['PROCESSOR_ARCHITECTURE'],
            'phpVersionNumeric' => PHP_VERSION_ID,
            'phpVersion' => phpversion()
        ];
    }
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
            echo ">";
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