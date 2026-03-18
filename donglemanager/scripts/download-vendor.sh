#!/bin/bash
#
# Download Chart.js and Font Awesome into assets/vendor for offline use.
# Run this ONCE on a machine with internet, then copy the entire
# donglemanager folder to the server — no internet needed on the server.
#
# Usage: ./download-vendor.sh
#        (run from donglemanager directory, or from project root)
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MODULE_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
VENDOR_DIR="$MODULE_DIR/assets/vendor"

mkdir -p "$VENDOR_DIR"
cd "$VENDOR_DIR"

echo "=== Dongle Manager: Downloading vendor assets (offline bundle) ==="

# Chart.js 4 (UMD build for <script> tag)
CHART_URL="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"
echo "Downloading Chart.js..."
curl -fsSL -o chart.umd.min.js "$CHART_URL"
echo "  -> chart.umd.min.js OK"

# Font Awesome 6 (CSS + webfonts)
FA_VERSION="6.5.1"
FA_ZIP="fontawesome-free-${FA_VERSION}-web.zip"
FA_URL="https://use.fontawesome.com/releases/v${FA_VERSION}/${FA_ZIP}"
echo "Downloading Font Awesome ${FA_VERSION}..."
curl -fsSL -o "$FA_ZIP" "$FA_URL"
echo "  -> Extracting..."
unzip -o -q "$FA_ZIP"
mkdir -p fontawesome
cp -r "fontawesome-free-${FA_VERSION}-web/css" fontawesome/
cp -r "fontawesome-free-${FA_VERSION}-web/webfonts" fontawesome/
rm -rf "fontawesome-free-${FA_VERSION}-web" "$FA_ZIP"
echo "  -> fontawesome/css + fontawesome/webfonts OK"

echo ""
echo "=== Done. Module is now fully offline. ==="
echo "Copy the entire donglemanager folder to your server — no internet required."
