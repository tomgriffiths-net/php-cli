<?php
$info['id_name'] = "";
$info['name'] = "";
$info['version'] = 1;
$info['author'] = "";
$info['description'] = "";
$info['dependencies'] = [];

$dir = "packages/" . $info['id_name'];
mkdir($dir,0777,true);
file_put_contents($dir . '/information.json',json_encode($info,JSON_PRETTY_PRINT));
file_put_contents($dir . '/main.php','<?php
class ' . $info['id_name'] . '{
    //public static function command($line):void{}
    //public static function init():void{}
}');