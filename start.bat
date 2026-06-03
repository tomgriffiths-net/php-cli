@echo off
setlocal enabledelayedexpansion

cd /D "%~dp0"
set LAUNCHER_SCRIPT=%~nx0

:retry

if exist cli.php (
    
    where php >nul 2>&1
    if !errorlevel! == 0 (

        php core\updateini.php

        if !errorlevel! neq 0 (
            echo Error: Failed to set up php.ini settings.
            pause
            goto end
        )

        php cli.php %*

        set code=!errorlevel!
        if "!code!"=="5" (
            echo Restarting...
            goto retry
        )
        echo Exit code: !code!

        goto end

    ) else (

        echo Installing PHP 8.5

        powershell -c "& ([ScriptBlock]::Create((irm 'https://www.php.net/include/download-instructions/windows.ps1'))) -Version 8.5 -ThreadSafe $true"

        if !errorlevel! neq 0 (
            echo Error: PHP installation failed.
            pause
            goto end
        )

        echo PHP installed. Please re-run this script.
        pause
        goto end

    )

) else (

    echo Downloading PHP-CLI Zip
    
    powershell -command "$ProgressPreference = 'SilentlyContinue'; Invoke-WebRequest -OutFile latest.zip -Uri https://files.tomgriffiths.net/php-cli/updates/latest.zip"
    
    echo Unzipping PHP-CLI
    powershell -command "$ProgressPreference = 'SilentlyContinue'; Expand-Archive -Path latest.zip -DestinationPath ."
    del latest.zip
    
)

goto retry
:end