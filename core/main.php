<?php
mklog(1,'Took ' . (floor(microtime(true)*1000) - $startTime) / 1000 . ' seconds to start');
awaitUserInput:
$fileUsedAsInput = false;
if(is_string($arguments['command'])){
    $line = $arguments['command'];
}
elseif(is_string($arguments['use-file-as-input'])){
    $fileUsedAsInput = true;
    if(is_file($arguments['use-file-as-input'])){
        $line = file_get_contents($arguments['use-file-as-input']);
        if(!is_string($line) || empty($line)){
            mklog(2,'Failed to read command from ' . $arguments['use-file-as-input']);
            $line = false;
        }
    }
    else{
        $line = false;
    }
}
else{
    echo ">";
    $line = user_input::await();
}

$commands = explode(" && ", $line);

foreach($commands as $command){
    cli_run($command);
}

if($arguments['no-loop'] === true){
    exit;
}
else{
    $arguments['command'] = false;
}
if($fileUsedAsInput){
    if($line !== false){
        if(!unlink($arguments['use-file-as-input'])){
            mklog(3,'Failed to delete executed command file ' . $arguments['use-file-as-input']);
        }
    }
    sleep($arguments['file-as-input-delay']);
}
goto awaitUserInput;

function cli_run(string $line):bool{
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