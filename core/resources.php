<?php
class cli_formatter{
    public static function fill(string $colour, int $width=120, int $height=29):void{
        self::clear();
        $string = '';
        $i = 1;
        while($i < $height+1){
            $string .= str_repeat(" ",$width);
            if($i < $height){
                $string .= "\n";
            }
            $i++;
        }
        echo self::formatLine($string,$colour,false,true,"reverse");
    }
    public static function ding():void{
        echo "\007";
    }
    public static function formatLine(string $string, string|bool $colour = false, string|bool $background = false, bool $newline = true, string|bool $attributes = false):string{
        $colourValues = array(
            'bold'         => '1',    'dim'          => '2',
            'black'        => '0;30', 'dark_gray'    => '1;30',
            'blue'         => '0;34', 'light_blue'   => '1;34',
            'green'        => '0;32', 'light_green'  => '1;32',
            'cyan'         => '0;36', 'light_cyan'   => '1;36',
            'red'          => '0;31', 'light_red'    => '1;31',
            'purple'       => '0;35', 'light_purple' => '1;35',
            'brown'        => '0;33', 'yellow'       => '1;33',
            'light_gray'   => '0;37', 'white'        => '1;37',
            'normal'       => '0;39'
        );
        $backgroundValues = array(
            'black'        => '40',   'red'          => '41',
            'green'        => '42',   'yellow'       => '43',
            'blue'         => '44',   'magenta'      => '45',
            'cyan'         => '46',   'light_gray'   => '47',
        );
        $attributeValues = array(
            'underline'    => '4',    'blink'        => '5', 
            'reverse'      => '7',    'hidden'       => '8',
        );
    
        $output = '';
    
        if($colour !== false){
            if(isset($colourValues[$colour])){
                $output .= "\033[";
                $output .= $colourValues[$colour] . "m";
            }
        }
    
        if($background !== false){
            if(isset($backgroundValues[$background])){
                $output .= "\033[";
                $output .= $backgroundValues[$background] . "m";
            }
        }
    
        if($attributes !== false){
            $attributesArray = array();
            if(strpos($attributes,",") !== false){
                $attributesArray = explode(",",$attributes);
            }
            else{
                $attributesArray[0] = $attributes;
            }
            foreach($attributesArray as $attribute){
                if(isset($attributeValues[$attribute])){
                    $output .= "\033[";
                    $output .= $attributeValues[$attribute] . "m";
                }
            }
        }
    
        $output .= $string . "\033[0m";
    
        if($newline){
            $output .= "\n";
        }
    
        return $output;
    }
    public static function clear():void{
        echo chr(27) . chr(91) . 'H' . chr(27) . chr(91) . 'J';
    }
}
class cmd{
    public static function newWindow(string $command, bool $keepOpen = false){
        mklog(0,'Starting process with command ' . $command);
        $cmdMode = "c";
        if($keepOpen){
            $cmdMode = "k";
        }
        pclose(popen('start cmd.exe /' . $cmdMode . ' "' . $command . '"','r'));
    }
    public static function run(string $command, bool $silent=false, bool $returnOutput=false):bool|array{
        if($silent){
            $command .= "  >nul 2>&1";
        }

        exec($command,$output,$result);

        if($returnOutput){
            return $output;
        }

        if($result === 0){
            return true;
        }

        return false;
    }
    public static function returnNewWindow(string $command, int $retries = 10, int $retryInterval = 1):string|bool{
        $time = floor(microtime(true)*1000);
        $outFile = 'tmp_out_' . $time;
        self::newWindow($command . ' > ' . $outFile,false);
        $tries = 0;
        $lastSize = 0;

        while(true){
            sleep($retryInterval);
            if(is_file($outFile)){
                $outFileSize = filesize($outFile);
                echo $outFileSize . "\n";
                if($outFileSize > 0 && $lastSize > 0){
                    if($outFileSize === $lastSize){
                        $out = file_get_contents($outFile);
                        unlink($outFile);
                        return $out;
                    }
                }
                $lastSize = $outFileSize;
            }
            else{
                echo "0\n";
            }
            
            $tries++;
            if($tries > $retries){
                return false;
            }
        }

        return false;
    }
}
class commandline_list{
    public static function table(array $columnNames=array(),array $rowsData=array()):string{
        $width = 1;
        if(array_is_list($columnNames)){
            foreach($columnNames as $index => $text){
                $columnNames[$text] = strlen(trim($text));
                unset($columnNames[$index]);
            }
        }
        foreach($columnNames as $text => $length){
            $width += $length +4;
            $columnWidths[] = $length;
        }
        $output = self::tableLine($width);
        $output .= self::tableColumnTitle($columnNames) . "\n";
        $output .= preg_replace('/[^|]/',"-",self::tableColumnTitle($columnNames)) . "\n";

        foreach($rowsData as $rowData){
            $output .= self::tableColumn($rowData,$columnWidths) . "\n";
        }

        $output .= self::tableLine($width);

        return $output;
    }
    public static function stringLengthLimit(string $string, int $length):string{
        if($length < 3){
            $length = 3;
        }
        if(strlen(trim($string)) > $length){
            $string = substr($string,0,$length-2) . "..";
        }
        return $string;
    }
    public static function tableColumnTitle($array):string{
        $output = '|';
        foreach($array as $data => $length){
            $output .= ' ' . str_pad(self::stringLengthLimit(data_types::convert_to_string($data),$length),$length," ",STR_PAD_RIGHT) . '  |';
        }
        return $output;
    }
    public static function tableColumn($data,$columnWidths):string{
        $output = '|';
        $i = 0;
        foreach($columnWidths as $length){
            if(isset($data[$i])){
                $output .= ' ' . str_pad(self::stringLengthLimit(data_types::convert_to_string($data[$i]),$length),$length," ",STR_PAD_RIGHT) . '  |';
            }
            else{
                $output .= ' ' . str_repeat(" ",$length) . '  |';
            }
            $i++;
        }
        return $output;
    }
    public static function tableLine(int $width):string{
        return "|" . str_repeat("-",$width-2) . "|\n";
    }
}
class data_types{
    public static function string_to_float(string $string):float{
        //Check if string is a number
        if(is_numeric($string)){
            //Return value
            $return = $string;
        }
        else{
            //Return 0
            $return = 0;
        }
        //Convert return to float
        return floatval($return);
    }
    public static function string_to_integer(string $string):int{
        //Check if string is a number
        if(is_numeric($string)){
            //Return value
            $return = $string;
        }
        else{
            //Return 0
            $return = 0;
        }
        //Convert return to integer
        return intval($return);
    }
    public static function string_to_boolean(string $string):bool{
        //Assume that the value is false
        $return = false;
        //Check if string is "true"
        if($string === "true"){
            $return = true;
        }
        return $return;
    }
    public static function boolean_to_string(bool $boolean):string{
        $return = "false";
        if($boolean === true){
            $return = "true";
        }
        return $return;
    }
    public static function convert_string(string $value):int|float|bool|string{
        $return = $value;
        if(is_numeric($value)){
            //Check if the string contains a point
            if(strpos($value,'.')){
                //Convert string to float
                $return = self::string_to_float($value);
            }
            else{
                //Convert string to integer
                $return = self::string_to_integer($value);
            }
        }
        elseif($value === "true" || $value === "false"){
            //convert string to boolean
            $return = self::string_to_boolean($value);
        }
        return $return;
    }
    public static function convert_to_string(string|float|int|bool $value):string{
        $return = "";
        if(is_string($value)){
            $return = $value;
        }
        elseif(is_float($value) || is_int($value)){
            $return = (string) $value;
        }
        elseif(is_bool($value)){
            $return = self::boolean_to_string($value);
        }

        return $return;
    }
    public static function xmlStringToArray(string $xml):array{
        $xml1 = simplexml_load_string($xml);
        return json_decode(json_encode($xml1),true);
    }
    public static function array_to_eval_string(array $data):string{
        $string = 'array(';
        foreach($data as $key => $value){
            if(is_int($key)){
                $keytext = $key;
            }
            else{
                $keytext = '"' . $key . '"';
            }
            
            $valuetext = self::convert_to_eval_string($value);
            $string .= $keytext . '=>' . $valuetext . ',';
        }
        $string .= ')';
        return $string;
    }
    public static function convert_to_eval_string(array|string|float|int|bool $value):string{
        $return = "";
        if(is_array($value)){
            $return = self::array_to_eval_string($value);
        }
        elseif(is_string($value)){
            $return = '"' . $value . '"';
        }
        elseif(is_float($value) || is_int($value)){
            $return = (string) $value;
        }
        elseif(is_bool($value)){
            $return = self::boolean_to_string($value);
        }

        return $return;
    }
}
class downloader{
    private static $lastPercent = false;
    private static $lastBytes = false;
    private static $lastTime = false;
    public static function downloadFile(string $url,string $outFile):bool{
        $return = false;
        mklog('general','Downloading file ' . files::getFileName($url),false);
        if(files::ensureFolder(files::getFileDir($outFile))){
            $curl = curl_init();
            $file = fopen($outFile, 'wb');

            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_HEADER => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_FILE => $file,
                CURLOPT_PROGRESSFUNCTION => ['downloader', 'curlProgress'],
                CURLOPT_NOPROGRESS => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_FAILONERROR => true
            ]);

            self::$lastPercent = 0;
            self::$lastBytes = 0;
            self::$lastTime = floor(microtime(true)*1000);
            @curl_exec($curl);

            if(curl_errno($curl) === 0){
                $return = true;
                echo "\n";
            }
            else{
                mklog('warning','Download error: ' . curl_error($curl),false);
            }

            curl_close($curl);
            fclose($file);

            if(!is_file($outFile)){
                $return = false;
            }

            self::$lastPercent = false;
            self::$lastBytes = false;
            self::$lastTime = false;
        }
        
        return $return;
    }
    private static function curlProgress($resource, $download_size = 0, $downloaded = 0, $upload_size = 0, $uploaded = 0):void{
        if($download_size > 0 && $downloaded > 0) {
            $percent = ceil($downloaded * 100 / $download_size);
        }
        else{
            $percent = 0;
        }
        
        if(self::$lastPercent !== $percent){
            self::$lastPercent = $percent;

            $time = floor(microtime(true)*1000) - self::$lastTime;
            if($time > 0){
                $speed = ($downloaded - self::$lastBytes) / ($time/1000);
            }
            else{
                $speed = 0;
            }

            $barWidth = 20;
            $barFilled = intval(floor(($percent/100)*$barWidth));

            $string = "[" . str_repeat("#",$barFilled) . str_repeat(" ",$barWidth - $barFilled) . "] ";
            $string .= self::formatBytes($download_size) . " ";
            $string .= str_pad($percent,3," ",STR_PAD_LEFT) . "% ";
            $string .= self::formatBytes($speed) . "/s\r";
            echo $string;
        }
    }
    public static function formatBytes($bytes):string{
        $digits = strlen(round($bytes));
        $unit = "B ";

        if($digits > 12){
            $bytes = $bytes / (1024**4);
            $unit = "TB";
        }
        elseif($digits > 9){
            $bytes = $bytes / (1024**3);
            $unit = "GB";
        }
        elseif($digits > 6){
            $bytes = $bytes / (1024**2);
            $unit = "MB";
        }
        elseif($digits > 3){
            $bytes = $bytes / 1024;
            $unit = "KB";
        }
        return str_pad(round($bytes),3," ",STR_PAD_LEFT) . $unit;
    }
}
class extensions{
    public static function load(string $extensionName):bool{
        $phpIni = "php\\php.ini";
        $result = self::enableExtensions($phpIni,array($extensionName));
        if($result){
            mklog('general',"Extension '" . $extensionName . "' added to configuration, please restart PHP-CLI",false);
            return true;
        }
        else{
            mklog('warning',"Unable to add extension '" . $extensionName . "' to configuration",false);
            return false;
        }
    }
    public static function is_loaded(string $extensionName):bool{
        return extension_loaded($extensionName);
    }
    public static function ensure(string $extensionName):bool{
        if(self::is_loaded($extensionName)){
            return true;
        }
        else{
            return self::load($extensionName);
        }
    }
    private static function enableExtensions(string $phpIni, array $extensions):bool{
        $enabledExtensions = array();
        if(is_file($phpIni)){
            $file = file($phpIni);
            if(is_array($file)){
                foreach($file as $index => $line){
                    $line1 = substr(trim($line),0,strpos($line . " "," "));
                    $substr = substr($line1,0,10);
                    if($substr === ";extension" || $substr === "extension="){
                        foreach($extensions as $extension){
                            $line2 = "extension=" . $extension;
                            if($line1 === $line2){
                                $enabledExtensions[] = $extension;
                            }
                            elseif($line1 === ";" . $line2){
                                $file[$index] = str_replace(";","",$line);
                                $enabledExtensions[] = $extension;
                            }
                        }
                    }
                }
                foreach($extensions as $extension){
                    if(in_array($extension,$enabledExtensions)){
                        mklog('general',"Enabling extension  '" . $extension . "'",false);
                        files::copyFile("php\\ext\\php_" . $extension . ".dll","C:\\php\\ext\\php_" . $extension . ".dll");
                    }
                    else{
                        mklog('warning',"Unable to find extension entry '" . $extension . "' in " . $phpIni,false);
                        return false;
                    }
                }
                if(file_put_contents($phpIni,$file) !== false){
                    return true;
                }
            }
        }
        return false;
    }
    public static function command($line):void{
        $lines = explode(" ",$line);
        if($lines[0] === "ensure-default"){
            self::defaultExtensions();
        }
        else{
            echo "extensions: Command not found\n";
        }
    }
    public static function init():void{
        $phpIni = "php\\php.ini";
        if(!is_file($phpIni)){
            if(!self::defaultExtensions()){
                mklog('warning','Failed to load default extensions',false);
            }
        }
    }
    private static function defaultExtensions():bool{
        $phpIni = "php\\php.ini";
        if(!is_file($phpIni)){
            files::copyFile($phpIni . "-development",$phpIni);
        }
        if(self::enableExtensions($phpIni,array('curl','mysqli','openssl','sockets','zip'))){
            mklog('general','Restarting PHP-CLI',false);
            cmd::newWindow("php\\php.exe cli.php");
            exit;
        }
        return false;
    }
}
class files{
    public static function globRecursive(string $base, string $pattern, $flags = 0):array{
        $flags = $flags & ~GLOB_NOCHECK;
        
        if (substr($base, -1) !== DIRECTORY_SEPARATOR) {
            $base .= DIRECTORY_SEPARATOR;
        }
    
        $files = glob($base.$pattern, $flags);
        if (!is_array($files)) {
            $files = [];
        }
    
        $dirs = glob($base.'*', GLOB_ONLYDIR|GLOB_NOSORT|GLOB_MARK);
        if (!is_array($dirs)) {
            return $files;
        }
        
        foreach ($dirs as $dir) {
            $dirFiles = self::globRecursive($dir, $pattern, $flags);
            $files = array_merge($files, $dirFiles);
        }
    
        return $files;
    }
    public static function ensureFolder(string $dir):bool{
        if(is_dir($dir)){
            return true;
        }
        elseif(is_file($dir)){
            return false;
        }
        else{
            return self::mkFolder($dir);
        }
    }
    public static function mkFolder(string $path):bool{
        if(empty($path)){
            return false;
        }
        return mkdir($path,0777,true);
    }
    public static function mkFile(string $path, $data, $fopenMode = "w"):bool|int{
        $dir = self::getFileDir($path);
        if(!is_dir($dir)){
            self::mkFolder($dir);
        }
        $stream = fopen($path,$fopenMode);
        $return = fwrite($stream,$data);
        fclose($stream);
        return $return;
    }
    public static function getFileDir(string $path):string{
        $path = str_replace("/","\\",$path);
        $pos = strripos($path,"\\");
        $dir = substr($path,0,$pos);
        return $dir;
    }
    public static function getFileName(string $path):string{
        $path = str_replace("/","\\",$path);
        $pos = strripos($path,"\\");
        $file = substr($path,$pos+1);
        return $file;
    }
    public static function copyFile(string $pathFrom, string $pathTo):bool{
        $success = false;
        $dir = self::getFileDir($pathTo);
        if(!is_file($pathFrom)){
            goto end;
        }
        if(!is_dir($dir)){
            self::mkFolder($dir);
        }

        $success = copy($pathFrom,$pathTo);

        end:
        return $success;
    }
    public static function validatePath(string $path, bool $addquotes = false):string{
        $path = str_replace("/","\\",$path);
        if(strpos($path," ") && $addquotes){
            $path = '"' . $path . '"';
        }
        return $path;
    }
    public static function getFileExtension(string $fileName):string{
        $ext = "";
        $pos = strripos($fileName,".");
        if($pos !== false){
            $ext = substr($fileName,$pos+1);
        }
        return $ext;
    }
    public static function fileExtensionMimeTypes():array{
        return array(
            "bmp"   => "image/bmp",
            "gif"   => "image/gif",
            "ico"   => "image/vnd.microsoft.icon",
            "jpeg"  => "image/jpeg",
            "jpg"   => "image/jpeg",
            "png"   => "image/png",
            "svg"   => "image/svg+xml",
            "tiff"  => "image/tiff",
            "tif"   => "image/tiff",
            "webp"  => "image/webp",

            "css"   => "text/css",
            "csv"   => "text/csv",
            "ics"   => "text/calendar",
            "html"  => "text/html",
            "java"  => "text/x-java-source,java",
            "js"    => "text/javascript",
            "txt"   => "text/plain",

            "aac"   => "audio/x-aac",
            "m3u"   => "audio/x-mpegurl",
            "midi"  => "audio/midi",
            "mid"   => "audio/midi",
            "mp3"   => "audio/mp3",
            "mp4a"  => "audio/mp4",
            "oga"   => "audio/ogg",
            "ogg"   => "audio/ogg",
            "opus"  => "audio/opus",
            "wav"   => "audio/x-wav",
            "weba"  => "audio/webm",

            "3gp"   => "video/3gpp",
            "3g2"   => "video/3gpp2",
            "avi"   => "video/x-msvideo",
            "flv"   => "video/x-flv",
            "h264"  => "video/h264",
            "jpgv"  => "video/jpeg",
            "m4v"   => "video/x-m4v",
            "mxu"   => "video/vnd.mpegurl",
            "mpeg"  => "video/mpeg",
            "mp4"   => "video/mp4",
            "ogv"   => "video/ogg",
            "webm"  => "video/webm",

            "7z"    => "application/x-7z-compressed",
            "apk"   => "application/vnd.android.package-archive",
            "bin"   => "application/octet-stream",
            "bz"    => "application/x-bzip",
            "bz2"   => "application/x-bzip2",
            "cab"   => "application/vnd.ms-cab-compressed",
            "class" => "application/java-vm",
            "csh"   => "application/x-csh",
            "deb"   => "application/x-debian-package",
            "doc"   => "application/msword",
            "docx"  => "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "eot"   => "application/vnd.ms-fontobject",
            "epub"  => "application/epub+zip",
            "exe"   => "application/x-msdownload",
            "gz"    => "application/gzip",
            "jar"   => "application/java-archive",
            "json"  => "application/json",
            "mpkg"  => "application/vnd.apple.installer+xml",
            "odp"   => "application/vnd.oasis.opendocument.presentation",
            "ods"   => "application/vnd.oasis.opendocument.spreadsheet",
            "odt"   => "application/vnd.oasis.opendocument.text",
            "pdf"   => "application/pdf",
            "ppt"   => "application/vnd.ms-powerpoint",
            "pptx"  => "application/vnd.openxmlformats-officedocument.presentationml.presentation",
            "pub"   => "application/x-mspublisher",
            "rar"   => "application/x-rar-compressed",
            "sh"    => "application/x-sh",
            "swf"   => "application/x-shockwave-flash",
            "tar"   => "application/x-tar",
            "vsd"   => "application/vnd.visio",
            "xhtml" => "application/xhtml+xml",
            "xls"   => "application/vnd.ms-excel",
            "xlsx"  => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "xml"   => "application/xml",
            "xul"   => "application/vnd.mozilla.xul+xml",
            "zip"   => "application/zip",
        );
    }
}
class json{
    public static function addToFile($path,$entryKey,$entryValue,$addToTop=true):bool{
        $existing = self::readFile($path);
        if($addToTop === true){
            $new[$entryKey] = $entryValue;
        }
        foreach($existing as $key => $value){
            $new[$key] = $value;
        }
        if($addToTop === false){
            $new[$entryKey] = $entryValue;
        }
        return self::writeFile($path,$new,true);
    }
    public static function readFile($path,$createIfNonexistant=true,$expectedValues=array()):mixed{
        //Chech if file exists
        $existing = false;
        $logpath = $path;
        if(substr($path,0,4) === "http"){
            $logpath = "URL";
            extensions::ensure("openssl");
            $domain = substr($path,strpos($path,"//"));
            $domain = substr($domain,0,strpos($domain,"/"));
            $existing = true;
            $createIfNonexistant = false;

            $options = [
                "http" => [
                    "header" => "User-Agent: PHP-CLI json\r\n"
                ]
            ];
            $context = stream_context_create($options);
            $json = @file_get_contents($path,false,$context);
            goto afterfileopen;
        }
        else{
            $existing = is_file($path);
        }
        if($existing){
            //Check if file can be read
            $json = @file_get_contents($path);
            afterfileopen:
            if($json === false){
                //Error is file cannot be read
                mklog("warning","Failed to read from file: ". $logpath);
            }
            //If file can be read, return array of json values
            else{
                return json_decode($json,true);
            }
        }
        else{
            if($createIfNonexistant){
                mklog("general","Attempt made to read from nonexistant file: " . $logpath . ", creating file");
                txtrw::mktxt($path,json_encode($expectedValues,JSON_PRETTY_PRINT));
                return $expectedValues;
            }
            else{
                mklog("warning","Attempt made to read from nonexistant file: " . $logpath);
            }
        }
        return false;
    }
    public static function writeFile(string $path, mixed $value, bool $overwrite=false):bool{
        $json = json_encode($value,JSON_PRETTY_PRINT);
        if($json === false){
            return false;
        }
        return txtrw::mktxt($path,$json,$overwrite);
    }
}
class time{
    public static function stamp(){
        return floor(microtime(true));
    }
    public static function millistamp(){
        return floor(microtime(true)*1000);
    }
}
class timetest{
    public static function command($line):void{
        $startTime = time::millistamp();

        $line = str_replace("\\","\\\\",$line);

        $return = eval("return " . $line);

        echo "\nReturn: " . json_encode($return,JSON_PRETTY_PRINT) . "\n";

        $endTime = time::millistamp();
        echo "\nTime Taken: " . round(($endTime - $startTime)/1000,3) . " seconds.\n";
    }
}
class txtrw{
    public static function mktxt(string $file, string $content, bool $overwrite = false):bool{
        if(is_file($file)){
            $writeFile = false;
        }
        else{
            $writeFile = true;
        }
    
        if($overwrite === true){
            $writeFile = true;
        }
    
        if($writeFile){
            $dir = files::getFileDir($file);
            if($dir !== ""){
                files::ensureFolder($dir);
            }

            $f = @fopen($file,"w");
            if($f === false){
                mklog("warning","Unable to access file: ". $file);
                return false;
            }

            if(@fwrite($f,$content) === false){
                mklog("warning","Unable to write to file: ". $file);
                @fclose($f);
                return false;
            }

            @fclose($f);
            return true;
        }

        return false;
    }
    public static function readtxt(string $file):string|false{
        //Check if file exists
        if(!is_file($file)){
            //Create file if it does not exist
            mklog("general","Attempt made to read from file that does not exist, creating file with no contents");
            self::mktxt($file,"");
        }
        else{
            //Return file contents if it exists
            $filecontents = file_get_contents($file);
            if($filecontents === false){
                mklog("error","Unable to read from file: " . $file);
            }
            else{
                return $filecontents;
            }
        }
        return false;
    }
}
class user_input{
    public static function await($newline = false, $returnArray = false):string|array{
        if($newline){
            echo "\n";
        }
        $line = trim(fgets($GLOBALS['stdin']));
        $return = $line;
        if($returnArray){
            $return = explode(" ",$line);
        }
        return $return;
    }
    public static function yesNo():bool{
        start:
        echo "\ny/n >";
        $res = strtolower(self::await());
        if($res === "y"){
            return true;
        }
        elseif($res === "n"){
            return false;
        }
        else{
            goto start;
        }
        return false;
    }
}