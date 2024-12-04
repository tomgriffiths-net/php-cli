@echo off
cd /D "%~dp0"
echo Downloading php-cli latest
powershell -command "$ProgressPreference = 'SilentlyContinue'; Invoke-WebRequest -OutFile latest.zip -Uri https://files.tomgriffiths.net/php-cli/updates/latest.zip; Expand-Archive -Path latest.zip -DestinationPath ."
del latest.zip
start /b "" cmd /c del "%~f0"&exit /b