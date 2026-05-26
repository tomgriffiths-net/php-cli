#!/bin/bash

cd "$(dirname "$0")"

updated=0

# Usage: ensure_command <command> <apt-package>
ensure_command(){
    local cmd=$1
    local pkg=$2

    if ! command -v "$cmd" &>/dev/null; then

        echo "$cmd not found. Installing $pkg via apt..."

        if [ "$cmd" = "php" ]; then
            # Add the ondrej/php repository only if not already present.
            if ! grep -rq "ondrej/php" /etc/apt/sources.list /etc/apt/sources.list.d/ 2>/dev/null; then
                if [ $updated -eq 0 ]; then
                    sudo apt update -qq
                fi
                sudo apt install software-properties-common -y -qq
                sudo LC_ALL=C.UTF-8 add-apt-repository ppa:ondrej/php -y > /dev/null
                updated=0
            fi
        fi

        if [ $updated -eq 0 ]; then
            sudo apt update -qq
            updated=1
        fi
        sudo apt install "$pkg" -y -qq

        if ! command -v "$cmd" &>/dev/null; then
            echo "Error: $pkg installation failed."
            exit 1
        fi
        
        echo "$pkg installed successfully."
    fi
}


while true; do
    updated=0
    if [ -f "cli.php" ]; then
        # cli.php exists — ensure PHP is available, then run it
        ensure_command php php8.5

        php core/updateini.php
        if [ $? -ne 0 ]; then
            echo "Failed to set up settings"
            break
        fi

        LAUNCHER_SCRIPT="$0" php cli.php "$@"
        code=$?

        if [ $code -ne 5 ]; then
            echo "Exit code: $code"
            break
        fi
        echo "Restarting..."

    else
        # cli.php not found, download PHP-CLI and retry
        ensure_command curl curl
        ensure_command unzip unzip

        echo "Downloading PHP-CLI..."
        curl -L -o latest.zip "https://files.tomgriffiths.net/php-cli/updates/latest.zip"

        echo "Installing PHP-CLI..."
        unzip -o latest.zip -d .
        rm -f latest.zip
        # Loop back to the top (retry) to check for cli.php again
    fi

done