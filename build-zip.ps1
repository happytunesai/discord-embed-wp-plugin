# Build a clean ZIP for the WordPress plugin, including only runtime files
param(
    [string]$ZipName = "discord-embed-wp-plugin.zip"
)

$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$src = $root
$staging = Join-Path $root ".staging"
$zipPath = Join-Path $root $ZipName
$manifest = Join-Path $root "dist-include.txt"

if (Test-Path $staging) { Remove-Item $staging -Recurse -Force }
New-Item -ItemType Directory -Path $staging | Out-Null

# Copy only listed files/folders
if (!(Test-Path $manifest)) { throw "Missing dist-include.txt" }
Get-Content $manifest | ForEach-Object {
    $item = $_.Trim()
    if (-not $item) { return }
    $srcPath = Join-Path $src $item
    if (Test-Path $srcPath) {
        if ((Get-Item $srcPath).PSIsContainer) {
            Copy-Item $srcPath -Destination $staging -Recurse -Force
        } else {
            $destPath = Join-Path $staging $item
            $destDir = Split-Path -Parent $destPath
            if (!(Test-Path $destDir)) { New-Item -ItemType Directory -Path $destDir | Out-Null }
            Copy-Item $srcPath -Destination $destPath -Force
        }
    } else {
        Write-Warning "Not found: $item"
    }
}

# Remove existing zip
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

# Create zip
Add-Type -AssemblyName 'System.IO.Compression.FileSystem'
[System.IO.Compression.ZipFile]::CreateFromDirectory($staging, $zipPath)

Write-Host "Created: $zipPath"
