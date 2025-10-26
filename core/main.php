<?php
mklog(1,'Took ' . round(microtime(true) - cli::info()['startTime'], 3) . ' seconds to start');

cli::start();

class cli{
    private static $started = false;
    private static $aliases = [];

    public static function info():array{
        return [
            'version' => 100,
            'startTime' => $_SERVER['REQUEST_TIME_FLOAT'],
            'pcName' => $_SERVER['COMPUTERNAME'],
            'pcDrive' => $_SERVER['SystemDrive'],
            'cpuThreads' => $_SERVER['NUMBER_OF_PROCESSORS'],
            'cpuType' => $_SERVER['PROCESSOR_ARCHITECTURE'],
            'phpVersionNumeric' => PHP_VERSION_ID,
            'phpVersion' => phpversion()
        ];
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
    public static function run(string $line):bool{
        $return = false;
        if($line === "exit"){
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
            return self::run(self::$aliases[$baseCommand] . " " . $line);
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
                mklog(2,"The package " . $baseCommand . " does not have a command function\n");
            }
        }
        else{
            mklog(2,"Unknown Command: " . $baseCommand . "\n");
        }
        
        return $return;
    }
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
    public static function registerAlias(string $alias, string $command):bool{
        if(class_exists($alias) || isset(self::$aliases[$alias]) || $alias === "exit"){
            return false;
        }
        self::$aliases[$alias] = $command;
        return true;
    }

    private static function getCommands():array{
        global $arguments;

        if(is_string($arguments['command'])){
            $line = $arguments['command'];
        }
        elseif(is_string($arguments['use-file-as-input'])){
            if(!is_file($arguments['use-file-as-input'])){
                return [];
            }

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