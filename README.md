# PHP-CLI
PHP-CLI is a command line application that can have packages installed into it.

# Use
To use this program in your own project:
* Link back to original source (here or www.tomgriffiths.net).
* Do not claim this code as your own.

# Notices
You are able to edit the code but I am not responsible if it breaks after doing so. This software comes as is. There is no warranty.

All file referances in this documentation are from the base path that the program was installed to, some file paths may change if the user has specified a new path.

If you find any issues with the software please email email@tomgriffiths.net with the details of the issue.


# Usage

To start the program, double-click on the start.bat file in the folder you installed this program to, then a window called "PHP-CLI: current_directory" will open, when the program has finnished starting there will be a ">" character indecating that it is awaiting user input, now you can type any command you want to run.

Anything that uses services or installs an application or manages core os processes may require administrator permissions and may not specify when they are needed, please use admin permissions when managing services / websites and doing other tasks that require managing the operating system to avoid permission issues.


# Commands

- **cli new [command line arguments]**: Starts a new PHP-CLI window, command line arguments are optional.
- **cli reload**: Closes current PHP-CLI window and opens a new one, command line arguments are not copied but permissions are.
- **extensions ensure-default**: Applies default extensions, is run by default when opening PHP-CLI.
- **pkgmgr install [package_id]**: Installs a package.
- **pkgmgr list**: Lists the installed packages.
- **pkgmgr list desc**: Lists the installed packages with their latest description.
- **pkgmgr update**: Updates the installed packages.
- **pkgmgr update [package_id]**: Updates a specific package.
- **pkgmgr update-core**: Updates the core of PHP-CLI.
- **timetest [function string]**: Allows a function string to be given and then shows fuction return in json and the time taken in seconds.
- **exit**: Closes the current window.


# Arguments

The program can accept basic command line arguments, these arguments can be set when executing the cli.php script.

e.g. php\php.exe cli.php <arg1> <arg1 value> <arg2> <arg2 value>
The program can also accept arguments set in cli.php with the fileArguments variables at the top of the file.


    $fileArguments['argument_name'] = argument_value


Please note that command line arguments are strings converted into fitting data types whereas fileArguments have to be set correctly as the data types are not converted.

command line argument conversion examples:

    "false" -> false   (boolean)
    "true"  -> true    (boolean)
    "1"     -> 1       (integer)
    "0"     -> 0       (integer)
    "1.1"   -> 1.1     (float)
    "words" -> "words" (string)

- **first-time-startup**: Expects boolean, default is false (bool). Used by start.bat to indecate new install.
- **verbose-logging**: Expexts boolean, default is false (bool). Used to display more logs, set in fileArguments for most logs as it tiakes time to read command line arguments.
- **use-file-as-input**: Expects false (bool) or a file path (string), default is false (bool). When set to a file path, the program will attempt to execute the first line of the text file as a command and delete the file when the command has finnished, then wait a number of seconds before checking if the file exists again.
- **file-as-input-delay**: Expects integer, default is 10 (int). Specifies the number of seconds the program waits before checking if the use-file-as-input file exists again.
- **no-loop**: Expects boolean, default is false (bool). Specifies weather the program should exit or await user input after a command or group of commands have been executed.
- **command**: Expects false (bool) or full command (string). Specifies a command that should be run when startup has completed.


# Program Docs

- **mklog(string $type, string $message, bool $verbose=true)**: The function mklog creates a log with a specific type and message. The common types are: general, warning, error. The error type exits the program. Logs are found in the logs folder seperated by month. The verbose option is a boolean and if set to true then it will only make the log if verbose logging is enabled.

- **verboseLogging()**: The function verboseLogging returns the value of the verbose-logging argument.

- **cli_run(string $line)**: The function cli_run is the function called when a command is entered into the cli, any string put into $line will be run as if it were a command.

Note: Most cool stuff is only available in packages that will need to be downloaded using the pkgmgr command.

Available resources included with the core:

- **cmd::newWindow(string $command, bool $keepOpen):void**: Opens a new command prompt window with a specified command.
- **cmd::run(string $command, bool $silent, bool $returnOutput):bool|array**: Runs a cmd command and can optionally return the commands output as an array of lines.
- **commandline_list::table(array $columnNames, array $rowsData):string**: Returns a string that when shown displays a table.
- **commandline_list::stringLengthLimit(string $string, int $length):string**: Limits the length of a string.
- **data_types::string_to_float(string $string):float**: Converts string to float value.
- **data_types::string_to_integer(string $string):int**: Converts string to integer.
- **data_types::string_to_boolean(string $string):bool**: Converts string to boolean.
- **data_types::boolean_to_string(bool $boolean):string**: Converts boolean to string.
- **data_types::convert_string(string $value):int|float|bool|string**: Converts from string to best fitting data type.
- **data_types::convert_to_string(mixed $value):string**: Converts integer/float/boolean to string.
- **data_types::xmlStringToArray(string $xml):array**: Converts XML into an array.
- **downloader::downloadFile(string $url, string $file_save_name):bool**: Downloads a file with command line progress bar.
- **downloader::formatBytes(string/int $bytes):string**: Formats bytes into KB/MB/GB/TB.
- **extensions::load(string $extension_name):bool**: Sets an extension to be loaded when PHP-CLI is started.
- **extensions::is_loaded(string $extension_name):bool**: Returns true if extension name given is loaded, false otherwise.
- **extensions::ensure(string $extension_name):bool**: Makes sure an extension is loaded.
- **files::globRecursive(string $base, string $pattern, $flags):array**: Similar to glob function but processes subfolders aswell.
- **files::ensureFolder(string $directory):bool**: Creates a folder if it does not exist.
- **files::mkFolder(string $path):bool**: Creates a folder with 0777 permissions.
- **files::mkFile(string $path, string $data, string $fopen_mode):bool|int**: Writes data to a file, basically just fopen, fwrite and fclose in one function with ensureFolder.
- **files::getFileDir(string $path):string**: Returns path without file name.
- **files::getFileName(string $path):string**: Returns file name.
- **files::copyFile(string $from, string $to):bool**: Copies a file from one place to another.
- **files::validatePath(string $path, bool $add_quotes):string**: Makes sure a path is valid, useful for passing path to command line.
- **files::getFileExtension(string $path):string**: Returns file extension from a given path.
- **json::addToFile(string $path, mixed $key, mixed $value, bool $addToTop):void**: Adds data to json file, only works when json file is an array.
- **json::readFile(string $path, bool $create, mixed $expected):mixed**: Reads data from json file.
- **json::writeFile(string $path, mixed $data, bool $overwrite):bool**: Writes data to json file.
- **time::stamp():int**: Returns unix time stamp.
- **time::millistamp():int**: Returns unix time stamp in milliseconds.
- **txtrw::mktxt(string $file, string $content, bool $overwrite):bool**: Writes data to a text file.
- **txtrw::readtxt(string $file):string/false**: Reads data from a text file.
- **user_input::await(bool $newLine, bool $returnArray):string|array**: Waits for user input, can return as array of words.
- **user_input::yesNo():bool**: Asks the user for y/n (yes/no) input.
