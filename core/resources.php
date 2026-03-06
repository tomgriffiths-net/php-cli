<?php
/**
 * Allows formatting text in the command line.
 */
class cli_formatter{
    /**
     * Fills the command line with a certain color.
     *
     * @param string $colour The color to fill the cmd window with.
     * @param integer $width The number of columns in the cmd window.
     * @param integer $height The number of rows in the cmd window.
     * @return void
     */
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
    /**
     * Makes the windows warning noise.
     *
     * @return void
     */
    public static function ding():void{
        echo "\007";
    }
    /**
     * Formats a string using command line styles.
     *
     * @param string $string The string to format.
     * @param boolean $colour The color of the text.
     * @param boolean $background The background color for the text.
     * @param boolean $newline Weather to add a newline character at the end of the string.
     * @param boolean $attributes A comma seperated list with no spaces of style attributes.
     * @return string The formatted string.
     */
    public static function formatLine(string $string, string|bool $colour = false, string|bool $background = false, bool $newline = true, string|bool $attributes = false):string{

        //Based on https://gist.github.com/donatj/1315354

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
    /**
     * Clears the text in the cmd window.
     *
     * @return void
     */
    public static function clear():void{
        echo chr(27) . chr(91) . 'H' . chr(27) . chr(91) . 'J';
    }
}
/**
 * Allows some basic command execution.
 */
class cmd{
    /**
     * Runs a command in a new cmd window.
     *
     * @param string $command The command to run.
     * @param boolean $keepOpen Weather to keep the new cmd window open.
     * @return void
     */
    public static function newWindow(string $command, bool $keepOpen=false):void{
        mklog(0, 'Starting process with command ' . $command);
        $cmdMode = "c";
        if($keepOpen){
            $cmdMode = "k";
        }
        pclose(popen('start cmd.exe /' . $cmdMode . ' "' . escapeshellcmd($command) . '"','r'));
    }
    /**
     * Runs a command using exec().
     *
     * @param string $command The command to be run.
     * @param boolean $silent Weather to redirect the command stdout and stderr to null.
     * @param boolean $returnOutput Weather to return the output of the command.
     * @param int $expectedExit The expected exit code for the command.
     * @return boolean|array If returnOutput is false, the return is a boolean that is true if the commands exit code was equal to the expected exit code, if returnOutput is true then an array of lines is returned.
     */
    public static function run(string $command, bool $silent=false, bool $returnOutput=false, int $expectedExit=0):bool|array{
        if($silent){
            $command .= "  >nul 2>&1";
        }

        mklog(0, "Running command " . $command);

        exec($command, $output, $result);

        if($returnOutput){
            return $output;
        }

        if($result === $expectedExit){
            return true;
        }

        return false;
    }
    /**
     * @deprecated
     */
    public static function returnNewWindow(string $command):string|false|null{
        return shell_exec($command);
    }
}
/**
 * Allows you to create command line tables.
 */
class commandline_list{
    /**
     * Turns some data into a table.
     *
     * @param array $columnNames Either a list of column names or an array of column names as keys and column widths as values.
     * @param array $rowsData A list containing row information, each row information is a list of strings to put into the table cells.
     * @return string The table.
     */
    public static function table(array $columnNames=[], array $rowsData=[]):string{
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
        $output .= self::tableRow($columnNames) . "\n";
        $output .= preg_replace('/[^|]/', "-", self::tableRow($columnNames)) . "\n";

        foreach($rowsData as $rowData){
            $output .= self::tableRow($rowData,$columnWidths) . "\n";
        }

        $output .= self::tableLine($width);

        return $output;
    }
    /**
     * Limits the length of a string, if the string is over the given length, the string will be cut off at -2 length and have ".." added so the total length is the given length.
     *
     * @param string $string The string to be limited.
     * @param integer $length The desired length.
     * @return string The limited string.
     */
    public static function stringLengthLimit(string $string, int $length):string{
        if($length < 3){
            $length = 3;
        }
        if(strlen(trim($string)) > $length){
            $string = substr($string,0,$length-2) . "..";
        }
        return $string;
    }
    /**
     * Takes in a row of information and turns it into a string representing the row.
     *
     * @param array $data Either a list of values to be put into the table, or an array where the keys are the values to put into the table and the values are the width of the columns.
     * @param array $columnWidths A list of column widths, only used when $data is a list.
     * @return string
     */
    public static function tableRow(array $data, array $columnWidths=[]):string{
        if(!array_is_list($data)){
            $columnWidths = array_values($data);
            $data = array_keys($data);
        }
        elseif(count($columnWidths) < count($data)){
            return "ERROR";
        }

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
    /**
     * Generates a horizontal table seperator.
     *
     * @param integer $width The width of the table.
     * @return string The seperator string.
     */
    public static function tableLine(int $width):string{
        return "|" . str_repeat("-",$width-2) . "|\n";
    }
}
/**
 * Type management.
 */
class data_types{
    /**
     * Converts a string to a fitting type.
     *
     * @param string $value The string to convert.
     * @return integer|float|boolean|string The converted value.
     */
    public static function convert_string(string $value):int|float|bool|string{
        $return = $value;
        if(is_numeric($value)){
            //Check if the string contains a point
            if(strpos($value,'.')){
                //Convert string to float
                $return = floatval($value);
            }
            else{
                //Convert string to integer
                $return = intval($value);
            }
        }
        elseif(in_array(strtolower($value), ["true","false"])){
            //convert string to boolean
            $return = strtolower($value) === "true";
        }
        return $return;
    }
    /**
     * Converts a string/float/integer/boolean to a string.
     *
     * @param string|float|integer|boolean $value The value to be converted.
     * @return string The value as a string.
     */
    public static function convert_to_string(string|float|int|bool $value):string{
        $return = "";
        if(is_string($value)){
            $return = $value;
        }
        elseif(is_float($value) || is_int($value)){
            $return = (string) $value;
        }
        elseif(is_bool($value)){
            $return = $value ? "true" : "false";
        }

        return $return;
    }
    /**
     * Converts some xml into a readable array.
     *
     * @param string $xml The xml string.
     * @return array The decoded values.
     */
    public static function xmlStringToArray(string $xml):array{
        $xml1 = simplexml_load_string($xml);
        return json_decode(json_encode($xml1),true);
    }
    /**
     * Converts an array into a string representation that can be given to eval.
     *
     * @param array $data The data to convert.
     * @return string The core string representation.
     */
    public static function array_to_eval_string(array $data):string{
        $string = '[';
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
        $string = substr($string, 0, -1) . ']';
        return $string;
    }
    /**
     * Converts a value into a code string representation.
     *
     * @param array|string|float|integer|boolean $value The value to be converted.
     * @return string The code string representation.
     */
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
            $return = $value ? "true" : "false";
        }

        return $return;
    }
    /**
     * Checks if a given array matches a template.
     *
     * @param array $data The data to test.
     * @param array $expected An array with the same keys and structure as the expected data, but the values are either arrays or a string saying the type of data (from gettype()) that should be there.
     * @return boolean Weather the data matched the expected layout and types.
     */
    public static function validateData(array $data, array $expected):bool{
        foreach($expected as $expectedName => $expectedType){
            if(!isset($data[$expectedName])){
                return false;
            }

            if(is_array($expectedType)){
                if(!is_array($data[$expectedName])){
                    return false;
                }

                if(!self::validateData($data[$expectedName], $expectedType)){
                    return false;
                }

                continue;
            }
            
            if(gettype($data[$expectedName]) !== $expectedType){
                return false;
            }
        }

        return true;
    }
}
/**
 * Download a file.
 */
class downloader{
    /**
     * Downloads a file and shows a progress bar.
     *
     * @param string $url The url to download from.
     * @param string $outFile The file to save the data to.
     * @return boolean Indicates success.
     */
    public static function downloadFile(string $url, string $outFile):bool{
        mklog(1, 'Downloading file ' . basename($url));

        if(is_file($outFile)){
            mklog(2, 'The download destination already exists');
            return false;
        }

        $destDir = files::getFileDir($outFile);
        if(!empty($destDir)){
            if(!files::ensureFolder($destDir)){
                return false;
            }
        }

        $return = false;
        
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

        mklog(0, "Retreiving file from " . $url);
        @curl_exec($curl);

        if(curl_errno($curl) === 0){
            $return = true;
            echo "\n";
        }
        else{
            mklog(2, 'Download error: ' . curl_error($curl));
        }

        if(!@fclose($file)){
            mklog(2, 'Failed to close output file ' . $outFile);
        }

        if(!is_file($outFile)){
            mklog(2, 'The output file ' . $outFile . ' doesnt exist after downloading');
            $return = false;
        }
        
        return $return;
    }
    private static function curlProgress($resource, $download_size = 0, $downloaded = 0, $upload_size = 0, $uploaded = 0):void{
        if($download_size > 0 && $downloaded > 0) {
            echo files::progressTracker($download_size, $downloaded);
        }
    }
}
/**
 * Use extension_enable and extension_ensure functions instead.
 * @deprecated
 */
class extensions{
    public static function load(string $extensionName):bool{
        extension_enable($extensionName);
        return false;
    }
    public static function is_loaded(string $extensionName):bool{
        return extension_loaded($extensionName);
    }
    public static function ensure(string $extensionName):bool{
        return extension_ensure($extensionName);
    }
}
/**
 * File management.
 */
class files{
    private static $progressStartTime = 0;
    private static $progressLastTotal = 0;
    private static $progressLocalStartTime = 0;
    private static $progressLocalCurrent = 0;
    private static $progressLastCurrent = 0;

    /**
     * Similar to glob but also recursively calls glob on subfolders too.
     *
     * @param string $base The base path.
     * @param string $pattern The pattern to test for inside.
     * @param integer $flags glob() flags.
     * @return array A list of all the files that were found, this does not include the sub folder names.
     */
    public static function globRecursive(string $base, string $pattern, int $flags=0):array{
        mklog(1, "Recursivly globbing folder " . $base);

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
    /**
     * Makes sure a folder exists by creating it if it doesnt exist.
     *
     * @param string $dir The folder to check.
     * @return boolean True if the folder already existed or exists now, false on failure.
     */
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
    /**
     * Creates a folder.
     *
     * @param string $path The path of the folder to create, the folders parent does not need to exist to create it.
     * @return boolean Indicates success.
     */
    public static function mkFolder(string $path):bool{
        if(empty($path)){
            return false;
        }

        if(is_dir($path)){
            mklog(2, 'Failed to create directory ' . $path . ' as it already exists');
            return false;
        }
        if(is_file($path)){
            mklog(2, 'Failed to create directory ' . $path . ' as a file exists with the same name');
        }

        mklog(0, "Creating directory " . $path);

        return mkdir($path, 0777, true);
    }
    /**
     * Creates a file, the parent directory does not need to exist to create the file.
     *
     * @param string $path The path of the file.
     * @param string $data The data to put into the file.
     * @param string $fopenMode What mode fopen should use.
     * @param boolean $overwrite Weather to overwrite any existing file.
     * @return boolean Indicates success.
     */
    public static function mkFile(string $path, string $data, string $fopenMode="w", bool $overwrite=true):bool{
        if(empty($path)){
            mklog(2, 'Cannot create a file with empty path');
            return false;
        }

        if(!in_array($fopenMode, ['w', 'wb', 'a', 'ab', 'x', 'xb', 'c', 'cb'])){
            mklog(2, 'Invalid fopen mode: ' . $fopenMode);
            return false;
        }

        if(!$overwrite && is_file($path)){
            mklog(2, 'Cannot create file ' . $path . ' as it already exists and overwrite is set to false');
            return false;
        }

        $dir = self::getFileDir($path);
        if(!empty($dir) && !is_dir($dir)){
            if(!self::mkFolder($dir)){
                mklog(2, 'Failed to create ' . $dir . ' directory when creating file');
                return false;
            }
        }

        mklog(0, 'Opening file ' . $path . ' with fopen mode ' . $fopenMode);

        $stream = fopen($path, $fopenMode);
        if(!$stream){
            mklog(2, 'Failed to open file ' . $path . ' with mode ' . $fopenMode);
            return false;
        }

        $return = true;
        $length = strlen($data);
        $bytes = fwrite($stream, $data);
        if($bytes === false){
            mklog(2, 'Failed to write data to file ' . $path);
            $return = false;
        }
        elseif($bytes !== $length){
            mklog(2, 'Failed to write correct amount of data to file ' . $path . ' (' . $bytes . ' out of ' . $length . ' bytes)');
            $return = false;
        }

        if(fclose($stream)){
            mklog(0, 'Closed file ' . $path);
        }
        else{
            mklog(2, 'Failed to close/save file ' . $path);
            $return = false;
        }
        
        return $return;
    }
    /**
     * Removes the last name in a file path.
     *
     * @param string $path The full file path.
     * @return string The path without the last name.
     */
    public static function getFileDir(string $path):string{
        $path = str_replace("/","\\",$path);
        $pos = strripos($path,"\\");
        $dir = substr($path,0,$pos);
        return $dir;
    }
    /**
     * Use basename instead.
     *
     * @param string $path
     * @return string
     */
    public static function getFileName(string $path):string{
        return basename($path);
    }
    /**
     * Copies a file from one place to another.
     *
     * @param string $pathFrom The source file.
     * @param string $pathTo The destination file, the destination file's folder will be created if it doesnt already exist.
     * @param boolean $showProgress Weather to show a progress bar, this changes the copy from copy() to many fread and fwrites.
     * @return boolean Indicates success.
     */
    public static function copyFile(string $pathFrom, string $pathTo, bool $showProgress=true):bool{
        mklog(1, 'Copying file ' . $pathFrom . ' to ' . $pathTo);

        if(!is_file($pathFrom)){
            mklog(2, 'Cannot copy from nonexistant source ' . $pathFrom);
            return false;
        }

        if(is_file($pathTo)){
            mklog(2, 'The destination file already exists ' . $pathTo);
            return false;
        }

        $dir = self::getFileDir($pathTo);
        if(!empty($dir) && !is_dir($dir)){
            if(!self::mkFolder($dir)){
                mklog(2, 'Failed to create folder for destination file ' . $dir);
                return false;
            }
        }

        if($showProgress){
            mklog(0, 'Copying file in chunk/progress mode');

            $totalBytes = filesize($pathFrom);
            if(!$totalBytes){
                mklog(2, 'Failed to get size of file');
                return false;
            }

            $in = fopen($pathFrom, 'rb');
            if(!$in){
                mklog(2, 'Failed to open source stream');
                return false;
            }

            $out = fopen($pathTo, 'wb');
            if(!$out){
                mklog(2, 'Failed to open source stream');
                @fclose($in);
                return false;
            }

            $bytesCopied = 0;
            while(!feof($in)){
                $chunk = fread($in, 1024*1024);

                if($chunk === false || !fwrite($out, $chunk)){

                    mklog(2, ($chunk === false ? 'Failed to read chunk from source file' : 'Failed to write chunk to destination file'));

                    if(fclose($in)){
                        mklog(2, 'Failed to close input file');
                    }
                    
                    @fclose($out);
                    @unlink($pathTo);
                    return false;
                }

                $bytesCopied += strlen($chunk);
                
                echo self::progressTracker($totalBytes, $bytesCopied);
            }

            if($bytesCopied !== $totalBytes){
                mklog(2, 'Failed to copy all bytes');
                return false;
            }

            if(fclose($in)){
                mklog(2, 'Failed to close input file');
            }
            if(fclose($out)){
                mklog(2, 'Failed to close output file');
            }

            return true;
        }
        else{
            return copy($pathFrom, $pathTo);
        }
    }
    /**
     * Makes all slashes backslashes and adds quotes if enabled and needed.
     *
     * @param string $path The path to be made valid for a windows command.
     * @param boolean $addquotes Weather to add quotes if needed.
     * @return string The converted path.
     */
    public static function validatePath(string $path, bool $addquotes=false):string{
        $path = str_replace("/","\\",$path);
        if(strpos($path," ") && $addquotes){
            $path = '"' . $path . '"';
        }
        return $path;
    }
    /**
     * Gets the extension from the last .xyz part of a path.
     *
     * @param string $fileName The path or name of the file.
     * @return string The extension of the file.
     */
    public static function getFileExtension(string $fileName):string{
        $ext = "";
        $pos = strripos($fileName,".");
        if($pos !== false){
            $ext = substr($fileName,$pos+1);
        }
        return $ext;
    }
    /**
     * Returns an array which has lowercase file extensions as keys and mime types as values.
     *
     * @return array
     */
    public static function fileExtensionMimeTypes():array{
        return [
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
        ];
    }
    /**
     * Converts a number of bytes into a string with an appropriate unit.
     *
     * @param integer $bytes The number of bytes.
     * @return string The formatted string.
     */
    public static function formatBytes(int $bytes):string{
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

        if(intval($bytes) < 10){
            return round($bytes, 1) . $unit;
        }
        else{
            return round($bytes) . $unit;
        }
    }
    /**
     * Makes a string that is a probress bar that can be printed to the command line.
     *
     * @param float $precentage The current percentage the bar should show.
     * @param integer $barWidth The number of characters the bar should be made of.
     * @param integer $totalBytes The total number of bytes, 0 to not show total.
     * @param integer $bytesPerSecond The current speed to show, 0 to not show speed.
     * @param integer $secondsLeft The number of seconds left, 0 to not show time left.
     * @return string The progress bar string.
     */
    public static function progressBar(float $precentage, int $barWidth=30, int $totalBytes=0, int $bytesPerSecond=0, int $secondsLeft=0):string{
        if($precentage > 100 || $precentage < 0 || $barWidth < 1 || $barWidth > 90){
            return "";
        }

        $barFilled = intval(floor(($precentage/100)*$barWidth));

        $string = "[" . str_repeat("#",$barFilled) . str_repeat(" ",$barWidth - $barFilled) . "] ";
        $string .= $precentage . "% ";

        if($totalBytes){
            $string .= files::formatBytes($totalBytes) . " ";
        }
        if($bytesPerSecond){
            $string .= files::formatBytes($bytesPerSecond) . "/s ";
        }
        if($secondsLeft){
            $string .= gmdate("H:i:s", $secondsLeft) . " ";
        }

        return $string . "  \r";
    }
    /**
     * Repeatedly call this function and it will return a progress bar with calculated time left and speed.
     *
     * @param integer $total The total number of bytes in the operation.
     * @param integer $current The current number of bytes done.
     * @param integer $barWidth The width of the bar part.
     * @param boolean $showTotal Weather to show the total number of bytes.
     * @param boolean $showSpeed Weather to calculate the speed of current bytes change between calls.
     * @param boolean $showEta Weather to calculate the time left between calls.
     * @return string The current state of the progress bar.
     */
    public static function progressTracker(int $total, int $current, int $barWidth=30, bool $showTotal=true, bool $showSpeed=true, bool $showEta=true):string{
        if($total < 1 || $current < 0 || $current > $total || $barWidth > 90){
            return "";
        }
        
        if(self::$progressLastTotal !== $total){
            //Reset if total changes
            self::$progressStartTime = microtime(true);
            self::$progressLastTotal = $total;
            self::$progressLocalStartTime = self::$progressStartTime;
            self::$progressLocalCurrent = $current;
            self::$progressLastCurrent = 0;
        }

        $precentage = round(($current / $total) * 100);

        if($showSpeed){
            $currentDifference = $current - self::$progressLastCurrent;
            self::$progressLocalCurrent += $currentDifference;
            $timeDiff = microtime(true) - self::$progressLocalStartTime;
            $bytesPerSecond = round(self::$progressLocalCurrent / $timeDiff);

            if($timeDiff > 10){
                self::$progressLocalCurrent = 0;
                self::$progressLocalStartTime = microtime(true);
            }
        }
        else{
            $bytesPerSecond = 0;
        }

        if($showEta && $bytesPerSecond){
            $eta = round(($total - $current) / $bytesPerSecond);
        }
        else{
            $eta = 0;
        }

        self::$progressLastCurrent = $current;

        return files::progressBar($precentage, $barWidth, ($showTotal ? $total : 0), $bytesPerSecond, $eta);
    }
}
/**
 * Read and write json files.
 */
class json{
    private static $readCache = [];

    /**
     * Adds a value to an array inside a json file, this does not work with nested arrays.
     *
     * @param string $path The path to the json file.
     * @param integer|string $entryKey The new key to add to the json file.
     * @param mixed $entryValue The new value to put with the key.
     * @param boolean $addToTop Weather to add it before the existing data or after.
     * @return boolean Indicates success.
     */
    public static function addToFile(string $path, int|string $entryKey, mixed $entryValue, bool $addToTop=false):bool{
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
    /**
     * Reads a json file and returns the decoded values.
     *
     * @param string $path The path to the json file.
     * @param boolean $createIfNonexistant Weather to create the file if it does not exist.
     * @param mixed $expectedValue The default value to return and put into the file if it doesnt already exist.
     * @return mixed The value stored in the json file or the default value.
     */
    public static function readFile(string $path, bool $createIfNonexistant=false, mixed $expectedValue=[]):mixed{
        global $arguments; //json-read-cache-timeout
        if(!is_int($arguments['json-read-cache-timeout'])){
            $arguments['json-read-cache-timeout'] = 1;
        }
        if(!is_int($arguments['json-url-read-cache-timeout'])){
            $arguments['json-url-read-cache-timeout'] = 5;
        }

        $url = strtolower(substr($path,0,4)) === "http";
        $timeout = $url ? $arguments['json-url-read-cache-timeout'] : $arguments['json-read-cache-timeout'];

        if(isset(self::$readCache[$path]) && microtime(true) - self::$readCache[$path]['lasttime'] < $timeout){
            mklog(0, 'Reading from cached ' . ($url ? 'URL ' : 'file ') . $path);
            return self::$readCache[$path]['contents'];
        }

        mklog(0, 'Reading from ' . ($url ? 'URL ' : 'file ') . $path);

        if($url){
            if(!extension_ensure("openssl")){
                mklog(2, 'Cannot open urls unless openssl is enabled');
                return false;
            }

            $context = stream_context_create(["http" => ["header" => "User-Agent: PHP-CLI " . $_SERVER['COMPUTERNAME'] . "\r\n"]]);
            $json = @file_get_contents($path, false, $context);
        }
        else{
            if(is_file($path)){
                $json = @file_get_contents($path);
            }
            else{
                if($createIfNonexistant){
                    if(!txtrw::mktxt($path, json_encode($expectedValue, JSON_PRETTY_PRINT))){
                        mklog(2, 'Failed to create file while reading file ' . $path);
                    }
                    return $expectedValue;
                }
                else{
                    mklog(2, "Attempt made to read from nonexistant file " . $path);
                    return false;
                }
            }
        }

        if(!is_string($json)){
            mklog(2, 'Failed to read from ' . $path);
            return false;
        }

        $decoded = json_decode($json, true);

        if($decoded === NULL){
            mklog(2, 'Failed to decode json in file ' . $path);
            return false;
        }

        self::$readCache[$path]['lasttime'] = microtime(true);
        self::$readCache[$path]['contents'] = $decoded;

        return $decoded;
    }
    /**
     * Writes a value into a json file.
     *
     * @param string $path The path to the json file.
     * @param mixed $value The value to but into the json file.
     * @param boolean $overwrite Weather to overwrite any existing file.
     * @return boolean Indicates success.
     */
    public static function writeFile(string $path, mixed $value, bool $overwrite=false):bool{
        mklog(0, 'Writing to file ' . $path);

        $json = json_encode($value, JSON_PRETTY_PRINT);
        if($json === false){
            return false;
        }

        if(!files::mkFile($path, $json, "w", $overwrite)){
            mklog(2, 'Failed to write to file ' . $path);
            return false;
        }

        self::$readCache[$path]['lasttime'] = microtime(true);
        self::$readCache[$path]['contents'] = $value;

        return true;
    }
}
/**
 * @deprecated
 */
class time{
    /**
     * Use time() instead.
     * @deprecated
     */
    public static function stamp(){
        return time();
    }
    /**
     * Use microtime(true) instead.
     * @deprecated
     */
    public static function millistamp(){
        return floor(microtime(true)*1000);
    }
}
/**
 * @internal
 */
class timetest{
    public static function command($line):void{
        $startTime = microtime(true);

        $line = str_replace("\\","\\\\",$line);

        $return = eval("return " . $line);

        echo "\nReturn: " . json_encode($return,JSON_PRETTY_PRINT) . "\n";

        $endTime = microtime(true);
        echo "\nTime Taken: " . round($endTime - $startTime,3) . " seconds.\n";
    }
}
/**
 * Read and write text files.
 */
class txtrw{
    /**
     * Creates a text file.
     *
     * @param string $file The path to the file.
     * @param string $content The content to put into the file.
     * @param boolean $overwrite Weather to overwrite any existing file.
     * @return boolean Indicates success.
     */
    public static function mktxt(string $file, string $content, bool $overwrite=false):bool{
        return files::mkFile($file, $content, "w", $overwrite);
    }
    /**
     * Reads a text file.
     *
     * @param string $file The path to the file.
     * @param boolean $createIfNonexistant Weather to create the file if it does not exist.
     * @return string|false The contents of the file on success or false on failure.
     */
    public static function readtxt(string $file, bool $createIfNonexistant=false):string|false{
        if(!is_file($file)){
            if($createIfNonexistant){
                mklog(0, 'Creating nonexistant file for reading with no contents');
                if(self::mktxt($file, "")){
                    return "";
                }
                else{
                    mklog(2, 'Failed to create file to read ' . $file);
                    return false;
                }
            }
            else{
                mklog(2, 'Failed to read from nonexistant file ' . $file);
                return false;
            }
        }
        else{
            $filecontents = file_get_contents($file);
            if(is_string($filecontents)){
                return $filecontents;
            }
            else{
                mklog(2, "Failed to read from file: " . $file);
                return false;
            }
        }
    }
    /**
     * Replace a specific line in a text file.
     *
     * @param string|array $input Either a list of lines or a path to a file.
     * @param string $starting What the line to be replaced starts with.
     * @param string $replacement The entire line replacement.
     * @param array $comments A list of symbols where lines beginning with any of these will be skipped when looking for which line to replace.
     * @return boolean|array If the input was a file path then a booleain indicating success will be returned, if the input was a list of lines, a list of lines will be returned.
     */
    public static function replaceLineBeginingWith(string|array $input, string $starting, string $replacement, array $comments=['#','//']):bool|array{
        if(is_string($input)){
            if(!is_file($input)){
                mklog(2, 'File ' . $input . ' does not exist');
                return false;
            }

            $lines = file($input);
            if(!is_array($lines)){
                mklog(2, 'Failed to read from file ' . $input);
                return false;
            }
        }
        else{
            if(!array_is_list($input)){
                mklog(2, 'Cannot use non list array for input');
                return false;
            }
            $lines = $input;
        }

        if(!array_is_list($comments)){
            mklog(2, 'Cannot use non list array for comments');
            return false;
        }

        $commentsLength = [];
        foreach($comments as $comment){
            $commentsLength[$comment] = strlen($comment);
        }
        unset($comments);
        
        $count = strlen($starting);
        $somethingHappened = false;
        foreach($lines as $index => $line){
            $line = trim($line);
            if(empty($line)){
                continue;
            }

            foreach($commentsLength as $comment => $commentLength){
                if(substr($line, 0, $commentLength) === $comment){
                    continue;
                }
            }

            if(substr($line,0,$count) === $starting){
                $lines[$index] = $replacement . "\n";
                $somethingHappened = true;
                break;
            }
        }

        if(!$somethingHappened){
            return false;
        }

        if(is_string($input)){
            return self::mktxt($input, implode($lines), true);
        }

        return $lines;
    }
}
/**
 * Reading command line input.
 */
class user_input{
    /**
     * Waits for the user to input something in the command line.
     *
     * @param boolean $newline Weather to output a newline for the user to type on.
     * @param boolean $returnArray Weather to parse the inputted line using cli::parseLine().
     * @return string|array The inputted string or the parseLine output.
     */
    public static function await($newline=false, $returnArray=false):string|array{
        if($newline){
            echo "\n";
        }

        $return = trim(fgets($GLOBALS['stdin']));

        if($returnArray){
            $return = cli::parseLine($return);
        }
        return $return;
    }
    /**
     * Asks the user to input yes (y) or no (n).
     *
     * @return boolean Weather the input was yes.
     */
    public static function yesNo():bool{
        $res = "";
        while(true){

            echo "\ny/n >";

            $res = substr(trim(strtolower(self::await())), 0, 1);

            if($res === "y"){
                return true;
            }
            elseif($res === "n"){
                return false;
            }
        }
    }
}

/**
 * Enables an extension in php.ini config file. A restart of PHP-CLI is required to actually enable the extension after this is called.
 *
 * @param string $extension The extension to enable.
 * @return boolean True if the config file was edited, false otherwise.
 */
function extension_enable(string $extension):bool{
    mklog(0, 'Enabling extension ' . $extension);

    if(!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $extension)){
        mklog(2, 'Invalid extension name ' . $extension);
        return false;
    }

    if(!txtrw::replaceLineBeginingWith('php/php.ini', ';extension=' . $extension, 'extension=' . $extension, [])){
        mklog(2, 'Could not enable extension ' . $extension);
        return false;
    }

    mklog(1, 'Enabled extension ' . $extension . ' in php\php.ini, please restart PHP-CLI for the changes to take effect');
    return true;
}
/**
 * Checks weather an extension is loaded, and calls extension_enable() if it is not allready loaded.
 *
 * @param string $extension The extension to check.
 * @return boolean True if the extension was allready loaded, false otherwise.
 */
function extension_ensure(string $extension):bool{
    if(extension_loaded($extension)){
        return true;
    }

    extension_enable($extension);
    return false;
}