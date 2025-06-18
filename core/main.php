<?php
mklog(1,'Took ' . (floor(microtime(true)*1000) - $startTime) / 1000 . ' seconds to start');

cli::start();

class cli{
    private static $started = false;
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
        elseif($line !== false){
            if(strpos($line," ") !== false){
                $spacePos = strpos($line," ");
                $baseCommand = substr($line,0,$spacePos);
                $line = substr($line,$spacePos+1);
            }
            else{
                $baseCommand = $line;
                $line = "";
            }
            if(class_exists($baseCommand)){
                if(method_exists($baseCommand,'command')){
                    $return = true;
                    try{
                        $baseCommand::command($line);
                    }
                    catch(Throwable $throwable){
                        mklog(2,"Something went wrong while trying to run: " . $baseCommand . " " . $line . " (" . substr($throwable,0,strpos($throwable,"\n")) . ")");
                        $return = false;
                    }
                }
                else{
                    echo "The package " . $baseCommand . " does not have a command function\n";
                }
            }
            else{
                echo "Unknown Command: " . $baseCommand . "\n";
            }
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
    public static function vardump():string|false{
        $new = [];
        
        foreach($GLOBALS as $name => $value){
            if($name !== "GLOBALS"){
                // Convert complex types to string representations
                if(is_array($value)){
                    $new[$name] = $value; // Arrays usually encode fine
                } elseif(is_object($value)){
                    $new[$name] = '[Object: ' . get_class($value) . ']';
                } elseif(is_resource($value)){
                    $new[$name] = '[Resource: ' . get_resource_type($value) . ']';
                } else {
                    $new[$name] = $value;
                }
            }
        }
        
        return json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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