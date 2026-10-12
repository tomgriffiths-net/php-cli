<?php
$info = [
    "id" => "my_package",
    "version" => "1.0.0",
    "author" => "me",
    "name" => "My Package v1.0.0",
    "dependencies" => [
        "id" => "another_package",
        "min" => "1.0.0"
    ],
    "minCliVersion" => "2.0.0",
    "minPhpVersion" => "8.3.0",
    "operatingSystems" => ["windows"],
    "architectures" => ["x64"],
    "globalName" => "My Package",
    "description" => "This is my package",
    "creationTime" => time()
];

$dir = "packages/" . $info['id'];
mkdir($dir,0777,true);
file_put_contents($dir . '/information.json',json_encode($info,JSON_PRETTY_PRINT));
file_put_contents($dir . '/main.php','<?php
class ' . $info['id'] . '{
    //public static function command(string $line):void{}
    //public static function init():void{}
}');