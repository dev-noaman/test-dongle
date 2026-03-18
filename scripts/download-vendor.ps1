# Download Chart.js and Font Awesome into assets/vendor for offline use.
# Run this ONCE on a machine with internet, then copy the entire
# donglemanager folder to the server — no internet needed on the server.
#
# Usage: .\download-vendor.ps1

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ModuleDir = Split-Path -Parent $ScriptDir
$VendorDir = Join-Path $ModuleDir "assets\vendor"

New-Item -ItemType Directory -Force -Path $VendorDir | Out-Null
Set-Location $VendorDir

Write-Host "=== Dongle Manager: Downloading vendor assets (offline bundle) ==="

# Chart.js 4
Write-Host "Downloading Chart.js..."
$chartUrl = "https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"
Invoke-WebRequest -Uri $chartUrl -OutFile "chart.umd.min.js" -UseBasicParsing
Write-Host "  -> chart.umd.min.js OK"

# Font Awesome 6
$faVersion = "6.5.1"
$faZip = "fontawesome-free-$faVersion-web.zip"
$faUrl = "https://use.fontawesome.com/releases/v$faVersion/$faZip"
Write-Host "Downloading Font Awesome $faVersion..."
Invoke-WebRequest -Uri $faUrl -OutFile $faZip -UseBasicParsing
Write-Host "  -> Extracting..."
Expand-Archive -Path $faZip -DestinationPath "." -Force
New-Item -ItemType Directory -Force -Path "fontawesome" | Out-Null
Copy-Item -Path "fontawesome-free-$faVersion-web\css" -Destination "fontawesome\" -Recurse -Force
Copy-Item -Path "fontawesome-free-$faVersion-web\webfonts" -Destination "fontawesome\" -Recurse -Force
Remove-Item -Recurse -Force "fontawesome-free-$faVersion-web", $faZip
Write-Host "  -> fontawesome\css + fontawesome\webfonts OK"

Write-Host ""
Write-Host "=== Done. Module is now fully offline. ==="
Write-Host "Copy the entire donglemanager folder to your server - no internet required."
