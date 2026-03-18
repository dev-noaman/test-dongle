#!/bin/bash
#
# Sign the Dongle Manager module for FreePBX (local signing).
# Run as root on the FreePBX server.
#

set -e

MODULE_PATH="/var/www/html/admin/modules/donglemanager"
DEVTOOLS_PATH="/usr/src/devtools"

# Ensure we're root
if [ "$(id -u)" -ne 0 ]; then
    echo "Run this script as root (e.g. sudo bash sign-module.sh)"
    exit 1
fi

# Clone devtools if missing
if [ ! -f "$DEVTOOLS_PATH/sign.php" ]; then
    echo "Cloning FreePBX devtools..."
    mkdir -p /usr/src
    git clone https://github.com/FreePBX/devtools "$DEVTOOLS_PATH"
fi

# Check module exists
if [ ! -d "$MODULE_PATH" ]; then
    echo "Error: Module not found at $MODULE_PATH"
    echo "Make sure donglemanager is installed (fwconsole ma install donglemanager)"
    exit 1
fi

# Find or create GPG key
KEY_ID=""
if gpg --list-secret-keys --keyid-format SHORT 2>/dev/null | grep -q sec; then
    KEY_ID=$(gpg --list-secret-keys --keyid-format SHORT 2>/dev/null | grep sec | head -1 | awk '{print $2}' | cut -d'/' -f2)
    echo "Using existing GPG key: $KEY_ID"
else
    echo "Generating new GPG key (no passphrase for automation)..."
    gpg --batch --gen-key <<EOF
Key-Type: RSA
Key-Length: 2048
Name-Real: Dongle Manager Module
Name-Email: local@localhost
Expire-Date: 0
%no-protection
%commit
EOF
    KEY_ID=$(gpg --list-secret-keys --keyid-format SHORT 2>/dev/null | grep sec | head -1 | awk '{print $2}' | cut -d'/' -f2)
    echo "Generated key: $KEY_ID"
fi

# Sign the module
echo "Signing donglemanager at $MODULE_PATH..."
"$DEVTOOLS_PATH/sign.php" "$MODULE_PATH" --local "$KEY_ID"

echo ""
echo "Done! The module is now locally signed."
echo "Refresh Module Admin in the FreePBX GUI - the unsigned warning should be gone."
