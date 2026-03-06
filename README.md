# PHP-CLI
PHP-CLI is a command line application that can have packages installed into it.

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

```php
$fileArguments['argument_name'] = "some_value";
$fileArguments['verbose-logging'] = true;
$fileArguments['json-read-cache-timeout'] = 10;
```


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
- **json-read-cache-timeout**: Expects integer, default is 1. Specifies how long to wait before forgetting a cached json file.
- **json-url-read-cache-timeout**: Expects integer, default is 5. Specifies how long to wait before forgetting a cached response from a web page that gives json.
- **check-syntax**: Expects boolean, default is false. Specifies weather to validate the php syntax in a packages main.php before loading it (using php -l), but this is slow.

# Package info
Example package info:
```json
{
    "id_name": "some_package",
    "name": "Some Package",
    "version": 5,
    "author": "a_human",
    "description": "This package does something cool :)",
    "dependencies": {
        "settings": 3,
        "network": 2
    }
}
```

# cli::info()
Example return:
```json
{
    "version": 103,
    "startTime": 1772574050.353874,
    "pcName": "TomsLaptop",
    "pcDrive": "C:",
    "cpuThreads": "16",
    "cpuType": "AMD64",
    "phpVersionNumeric": 80412,
    "phpVersion": "8.4.12"
}
```