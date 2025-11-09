# PHP-CLI
PHP-CLI is a command line application that can have packages installed into it.

To use this program in your own project:
* Link back to original source (here or www.tomgriffiths.net).
* Do not claim this code as your own.
* Installed packages also require valid attribution.

# Notices
You are able to edit the code but I am not responsible if it breaks after doing so. This software comes as is. There is no warranty.

All file referances in this documentation are from the base path that the program was installed to, some file paths may change if the user has specified a new path.

If you find any issues with the software please email email@tomgriffiths.net with the details of the issue.

# Usage
To start the program, double-click on the start.bat file in the folder you installed this program to, then a window called "PHP-CLI: current_directory" will open, when the program has finnished starting there will be a ">" character indecating that it is awaiting user input, now you can type any command you want to run.

Anything that uses services or installs an application or manages other processes may require administrator permissions and may not specify when they are needed, please use admin permissions when managing services / websites and doing other tasks that require managing the operating system to avoid permission issues.

# Commands
- **cli new [command line arguments]**: Starts a new PHP-CLI window, command line arguments are optional.
- **cli reload**: Closes current PHP-CLI window and opens a new one with the same permission level, command line arguments are not copied.
- **cli clear**: Clears the command window.
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

e.g. php\php.exe cli.php [arg1] [arg1 value] [arg2] [arg2 value]
The program can also accept arguments set in cli.php with the fileArguments variables at the top of the file.


    $fileArguments['argument_name'] = argument_value;


Please note that command line arguments are strings converted into fitting data types whereas fileArguments have to be set correctly as the data types are not converted.

command line argument conversion examples:

    "false" -> false   (boolean)
    "true"  -> true    (boolean)
    "1"     -> 1       (integer)
    "0"     -> 0       (integer)
    "1.1"   -> 1.1     (float)
    "words" -> "words" (string)

- **verbose-logging**: Expexts boolean, default is false (bool). Used to display more logs, set in fileArguments for most logs as it tiakes time to read command line arguments.
- **use-file-as-input**: Expects false (bool) or a file path (string), default is false (bool). When set to a file path, the program will attempt to execute the first line of the text file as a command and delete the file when the command has finnished, then wait a number of seconds before checking if the file exists again.
- **file-as-input-delay**: Expects integer, default is 10 (int). Specifies the number of seconds the program waits before checking if the use-file-as-input file exists again.
- **no-loop**: Expects boolean, default is false (bool). Specifies weather the program should exit or await user input after a command or group of commands have been executed.
- **command**: Expects false (bool) or full command (string). Specifies a command that should be run when startup has completed.


# Program Docs
- **mklog(int $type, string $message)**: Creates a log with a specific type and message. There are 4 types availabe, 0.Verbose, 1.General, 2.Warning, and 3.Error. Logs are found in the logs folder seperated by month. The verbose logs will only show when something has enabled verbose-logging.
- **verboseLogging():bool**: Returns the value of the verbose-logging argument.
- **cli::run(string $line, bool $captureOutput=false):bool|string**: Runs a command as if it were typed into the cli, returns true if the command exists or false on failure.
- **cli::info():array**: Returns useful information about the cli.
- **cli::registerAlias(string $alias, string $command):bool**: Registers an alias for a command, returns true on success or false on failure.
- **cli::parseLine(string $line):array**: Parses a command string into arguments, options and parameters. inputted "words" can be quoted with double-quotes to stop spaces making it 2 words. Arguments (args) are strings on their own, options are preceded with a "-" and are added to the options list if they exist, parameters are preceded with "--" and then take watever was behind them as the value, this is added to the params array and is keyed with the parameter name and has the parameter string value.
- **extension_enable(string $extensionName):bool**: Edits the php/php.ini file to enable a given extension on the next start, returns true on success or false on failure.
- **extension_ensure(string $extensionName):bool**: Uses php's built in extension_loaded() to check if a given extension is loaded, if it is not loaded then it will run extension_enable(), returns true if the extension was already loaded and false otherwise.

Note: Most cool stuff is only available in packages that will need to be downloaded using the pkgmgr command.

Available resources included with the core:

- **cli_formatter::fill(string $colour, int $width=120, int $height=29):void**: Fills the command line with a certain colour.
- **cli_formatter::ding():void**: Plays the windows warning noise.
- **cli_formatter::formatLine(string $string, string|bool $colour=false, string|bool $background=false, bool $newline=true, string|bool $attributes=false):string**: Returns a command line formatted string.
- **cli_formatter::clear():void**: Clears the command window.
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
- **data_types::validateData(array $data, array $expected):bool**: Uses isset and gettype on each element of an array, returns true if everything matches and false otherwise.
- **downloader::downloadFile(string $url, string $file_save_name):bool**: Downloads a file with command line progress bar.
- **files::globRecursive(string $base, string $pattern, $flags):array**: Similar to glob function but processes subfolders aswell.
- **files::ensureFolder(string $directory):bool**: Creates a folder if it does not exist.
- **files::mkFolder(string $path):bool**: Creates a folder with 0777 permissions.
- **files::mkFile(string $path, string $data, string $fopenMode="w", bool $overwrite=true):bool**: Writes data to a file, returns true on success or false on failure.
- **files::getFileDir(string $path):string**: Returns path without file name.
- **files::getFileName(string $path):string**: Returns file name.
- **files::copyFile(string $from, string $to):bool**: Copies a file from one place to another.
- **files::validatePath(string $path, bool $add_quotes):string**: Makes sure a path is valid, useful for passing path to command line.
- **files::getFileExtension(string $path):string**: Returns file extension from a given path.
- **files::formatBytes(string/int $bytes):string**: Formats bytes into KB/MB/GB/TB.
- **files::progressBar(float $precentage, int $barWidth=30, int $totalBytes=0, int $bytesPerSecond=0, int $secondsLeft=0):string**: Returns a formatted string with a progress bar and stats.
- **files::progressTracker(int $total, int $current, int $barWidth=30, bool $showTotal=true, bool $showSpeed=true, bool $showEta=true):string**: Returns a formatted string with a progress bar and stats, continuously call to update, calculates stats on its own.
- **json::addToFile(string $path, int|string $entryKey, mixed $entryValue, bool $addToTop=false):bool**: Adds data to json file, only works when json file is an array, returns true on success or false on failure.
- **json::readFile(string $path, bool $createIfNonexistant=false, mixed $expectedValue=[]):mixed**: Reads data from json file, returns the decoded data or false on failure.
- **json::writeFile(string $path, mixed $data, bool $overwrite):bool**: Writes data to json file, returns true on success or false on failure.
- **time::stamp():int**: Returns unix time stamp.
- **time::millistamp():int**: Returns unix time stamp in milliseconds.
- **txtrw::mktxt(string $file, string $content, bool $overwrite = false):bool**: Writes data to a text file, returns true on success or false on failure.
- **txtrw::readtxt(string $file, bool $createIfNonexistant=false):string|false**: Reads data from a text file, returns the text on success or false on failure.
- **txtrw::replaceLineBeginingWith(string|array $input, string $starting, string $replacement, array $comments=['#','//']):bool|array**: Replaces a line begining with something, if given an array as input then will return an array, otherwise will do the operation on a file instead, skips lines begining with a comment, returns true or array on success or false on failure.
- **user_input::await(bool $newLine=false, bool $returnArray=false):string|array**: Waits for user input, returns an array of words (works with quotations) or a string.
- **user_input::yesNo():bool**: Asks the user for y/n (yes/no) input, returns true on yes or false otherwise.
