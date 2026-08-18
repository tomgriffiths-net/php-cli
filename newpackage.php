<?php
$info['id_name'] = "my_new_package";
$info['name'] = "My New Package";
$info['version'] = 1;
$info['author'] = "me";
$info['description'] = "It does something really cool";
$info['dependencies'] = [
    "some_other_package" => 1
];

$dir = "packages/" . $info['id_name'];
mkdir($dir,0777,true);
file_put_contents($dir . '/information.json',json_encode($info,JSON_PRETTY_PRINT));
file_put_contents($dir . '/main.php','<?php
class ' . $info['id_name'] . '{
    //public static function command($line):void{}
    //public static function init():void{}
}');