@echo off

cd /D "%~dp0"

:retry

if exist cli.php (
    
    if exist php\php.exe (

        php\php.exe cli.php

    ) else (

        echo Downloading custom ini PHP
        powershell -command "$ProgressPreference = 'SilentlyContinue'; Invoke-WebRequest -OutFile php.zip -Uri 'https://files.tomgriffiths.net/php-cli/files/php-8.4.12-Win32-vs17-x64-CustomIniV1.zip'"
        
        if not exist php.zip (
            echo Failed to download PHP
            pause
            exit
        )

        echo Installing PHP
        powershell -command "$ProgressPreference = 'SilentlyContinue'; Expand-Archive -Path php.zip -DestinationPath ./php"

        if not exist php\php.exe (
            echo Failed to unzip PHP
            pause
            exit
        )
        
        del php.zip
        echo Launching CLI
        php\php.exe cli.php

    )

) else (

    echo Downloading PHP-CLI
    
    powershell -command "$ProgressPreference = 'SilentlyContinue'; Invoke-WebRequest -OutFile latest.zip -Uri https://files.tomgriffiths.net/php-cli/updates/latest.zip"
    
    echo Installing PHP-CLI
    powershell -command "$ProgressPreference = 'SilentlyContinue'; Expand-Archive -Path latest.zip -DestinationPath ."
    del latest.zip
    
    goto retry

)