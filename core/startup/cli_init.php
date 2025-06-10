<?php
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