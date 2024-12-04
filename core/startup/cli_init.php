<?php
mklog('general','Setting window title');
exec('title PHP-CLI: ' . getcwd());

mklog('general','Loading stdin and stdout');
$stdout = fopen("php://stdout","w");
if($stdout === false){
    mklog('error','Unable to load stdout (E005)',false);
}
$stdin = fopen("php://stdin","r");
if($stdin === false){
    mklog('error','Unable to load stdin (E006)',false);
}