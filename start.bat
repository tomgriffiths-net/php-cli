@echo off

cd /D "%~dp0"

:retry

if exist cli.php (
    
    if exist php\php.exe (

        php\php.exe cli.php

    ) else (

        echo Downloading PHP
        powershell -command "$ProgressPreference = 'SilentlyContinue'; $link = (Invoke-WebRequest -Uri https://files.tomgriffiths.net/php-cli/php_latest.txt).Content; Invoke-WebRequest -OutFile php.zip -Uri $link"
        
        echo Installing PHP
        powershell -command "$ProgressPreference = 'SilentlyContinue'; Expand-Archive -Path php.zip -DestinationPath ./php"
        
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