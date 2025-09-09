<#
package-release.ps1

Erstellt eine ZIP‑Package des Plugins für WordPress Upload:
- schließt aus: alle .zip Dateien, .gitattributes, .gitignore, README.md, package-release.ps1
- erstellt ZIP mit korrekter WordPress Plugin-Struktur (Dateien im ZIP-Root)
- verwendet Plugin Text Domain als eindeutigen Identifier

Usage (PowerShell):
.\package-release.ps1

Das Skript arbeitet im Verzeichnis, in dem es liegt (Plugin-Root).
#>

# --- Konfiguration ---
$excludeNames = @('.gitattributes', '.gitignore', 'README.md')
$excludeExtensions = @('.zip')
$fileListName = 'package-filelist.txt'
# Output directory for zips
$outputDir = 'releases'

# Plugin identifiers - muss mit Plugin Header übereinstimmen
$pluginTextDomain = 'discord-embed-creator'  # Text Domain aus Plugin Header
$pluginSlug = 'discord-embed-creator'       # WordPress Plugin Slug (für Ordnername)

# Ensure the generated file list is not included in the archive
$excludeNames += $fileListName
# Output directory for zips
$outputDir = 'releases'

# Determine root (script location)
$scriptPath = $MyInvocation.MyCommand.Definition
$root = Split-Path -Parent $scriptPath
Push-Location $root

try {
    Write-Host "Working directory: $root"

    # Try to determine version from main plugin PHP file
    $pluginPhp = Join-Path $root 'discord-embed-plugin.php'
    $version = ''
    if (Test-Path $pluginPhp) {
        # Look for defined constant first
        $d = Select-String -Path $pluginPhp -Pattern "define\(\s*'DISCORD_EMBED_VERSION'\s*,\s*'(?<v>[^']+)'\s*\)" -AllMatches
        if ($d -and $d.Matches.Count -gt 0) {
            $version = $d.Matches[0].Groups['v'].Value
        } else {
            # fallback to plugin header 'Version:'
            $h = Select-String -Path $pluginPhp -Pattern "^\s*\*\s*Version:\s*(?<v>\S+)" -AllMatches
            if ($h -and $h.Matches.Count -gt 0) { $version = $h.Matches[0].Groups['v'].Value }
        }
    }

    if ([string]::IsNullOrWhiteSpace($version)) {
        $releaseId = Get-Date -Format yyyyMMdd-HHmmss
    } else {
        $releaseId = $version
    }

    # Ensure output dir exists
    if (-not (Test-Path $outputDir)) {
        New-Item -ItemType Directory -Path $outputDir | Out-Null
    }

    # Create a subfolder per release (version or timestamp)
    $releaseDir = Join-Path $outputDir $releaseId
    if (-not (Test-Path $releaseDir)) { New-Item -ItemType Directory -Path $releaseDir | Out-Null }

    # Fixed zip name mit Plugin Text Domain für eindeutige WordPress Identifikation
    $zipBaseName = "$pluginSlug.zip"
    $zipPath = Join-Path $releaseDir $zipBaseName

    Write-Host "Plugin Info:"
    Write-Host "  Text Domain: $pluginTextDomain"
    Write-Host "  Plugin Slug: $pluginSlug" 
    Write-Host "  Version: $releaseId"

    # Ensure any existing zip with same name is removed
    if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

    # Gather files to include - mit besserer Filterung und Existenz-Check
    $allFiles = Get-ChildItem -Path $root -Recurse -File -Force -ErrorAction SilentlyContinue | Where-Object {
        # exclude .git folder contents
        $_.FullName -notmatch "\\.git\\" -and
        # Stelle sicher, dass die Datei wirklich existiert und lesbar ist
        (Test-Path $_.FullName -PathType Leaf) -and
        # Prüfe dass es kein Symlink oder Placeholder ist und nicht leer ist
        $_.Length -ne $null -and $_.Length -gt 0
    }

    $filesToInclude = @()
    foreach ($f in $allFiles) {
        # Nochmalige Existenz-Prüfung vor Verarbeitung
        if (-not (Test-Path $f.FullName -PathType Leaf)) { 
            Write-Warning "Skipping non-existent file: $($f.FullName)"
            continue 
        }
        
        # Überspringe leere Dateien
        if ($f.Length -eq 0) {
            Write-Warning "Skipping empty file: $($f.FullName)"
            continue
        }
        
        if ($excludeNames -contains $f.Name) { continue }
        if ($excludeExtensions -contains $f.Extension.ToLower()) { continue }
        if ($f.FullName -match "\\\.git\\") { continue }
        # skip files inside output dir (korrigierte Pfad-Prüfung)
        $outputDirFullPath = (Resolve-Path (Join-Path $root $outputDir) -ErrorAction SilentlyContinue)
        if ($outputDirFullPath -and $f.FullName.StartsWith($outputDirFullPath.Path)) { continue }
        # Skip the package script itself
        if ($f.Name -eq (Split-Path -Leaf $scriptPath)) { continue }

        $filesToInclude += $f
    }

    # Ensure the generated file list is not included (in case an older copy exists in project root)
    $filesToInclude = $filesToInclude | Where-Object { $_.Name -ne $fileListName }

    # Write file list relative paths
    $relPaths = $filesToInclude | ForEach-Object { $_.FullName.Substring($root.Length+1) }
    $fileListPath = Join-Path $releaseDir $fileListName
    $relPaths | Out-File -FilePath $fileListPath -Encoding utf8
    Write-Host "Wrote file list to $fileListPath (count: $($relPaths.Count))"

    # Create zip from staging folder (include all files, preserve folders)
    # WordPress benötigt explizite Ordner-Einträge im ZIP!
    if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
    
    # Erstelle ZIP mit .NET Methoden für bessere Kontrolle über Ordnerstruktur
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    Add-Type -AssemblyName System.IO.Compression
    
    $zipStream = [System.IO.File]::Create($zipPath)
    $zip = New-Object System.IO.Compression.ZipArchive($zipStream, [System.IO.Compression.ZipArchiveMode]::Create)
    
    try {
        # Sammle alle benötigten Ordner aus den Dateipfaden
        $directories = @()
        foreach ($f in $filesToInclude) {
            $relative = $f.FullName.Substring($root.Length + 1)
            $dir = Split-Path $relative -Parent
            if ($dir -and $dir -ne '') {
                $directories += $dir
            }
        }
        
        # Entferne Duplikate und sortiere
        $uniqueDirs = $directories | Sort-Object | Get-Unique
        
        # Erstelle explizite Ordner-Einträge im ZIP (wichtig für WordPress!)
        foreach ($dir in $uniqueDirs) {
            $dirEntry = $dir.Replace('\', '/') + '/'
            Write-Host "Creating directory entry: $dirEntry"
            $entry = $zip.CreateEntry($dirEntry)
            $entry.LastWriteTime = (Get-Date)
        }
        
        # Füge alle Dateien hinzu
        foreach ($f in $filesToInclude) {
            if (-not (Test-Path $f.FullName -PathType Leaf)) {
                Write-Warning "Skipping file during ZIP creation - no longer exists: $($f.FullName)"
                continue
            }
            
            $relative = $f.FullName.Substring($root.Length + 1).Replace('\', '/')
            Write-Host "Adding file: $relative"
            
            # Erstelle Datei-Eintrag
            $entry = $zip.CreateEntry($relative)
            $entry.LastWriteTime = $f.LastWriteTime
            
            # Kopiere Dateiinhalt
            $entryStream = $entry.Open()
            $fileStream = [System.IO.File]::OpenRead($f.FullName)
            try {
                $fileStream.CopyTo($entryStream)
            } finally {
                $fileStream.Dispose()
                $entryStream.Dispose()
            }
        }
        
    } finally {
        $zip.Dispose()
        $zipStream.Dispose()
    }

    Write-Host "Created package: $zipPath"
    Write-Host "Files included: $($relPaths.Count)"
    Write-Host "Note: $fileListPath contains the exact included file list."
    Write-Host ""
    Write-Host "WordPress Upload Instructions:"
    Write-Host "1. Deaktiviere das bestehende Plugin in WordPress Admin"
    Write-Host "2. Lösche das bestehende Plugin komplett (WordPress Admin > Plugins > Löschen)"
    Write-Host "3. Lade $zipBaseName über WordPress Admin > Plugins > Plugin hochladen hoch"
    Write-Host "4. Aktiviere das Plugin"
    Write-Host ""
    Write-Host "WICHTIG: Altes Plugin zuerst löschen um Cache-Konflikte zu vermeiden!"

} catch {
    Write-Error $_.Exception.Message
} finally {
    Pop-Location
}
