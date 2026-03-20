<#
package-release.ps1

Creates a ZIP package of the plugin for WordPress upload:
- excludes: all .zip files, .gitattributes, .gitignore, README.md, package-release.ps1
- creates ZIP with correct WordPress plugin structure (files in ZIP root)
- uses plugin text domain as unique identifier

Usage (PowerShell):
.\package-release.ps1

The script works in the directory where it is located (plugin root).
#>

# --- Configuration ---
$excludeNames = @('.gitattributes', '.gitignore', 'README.md')
$excludeExtensions = @('.zip')
$fileListName = 'package-filelist.txt'
# Output directory for zips
$outputDir = 'releases'

# Plugin identifiers - must match plugin header
$pluginTextDomain = 'discord-embed-creator'  # Text Domain from Plugin Header
$pluginSlug = 'discord-embed-creator'       # WordPress Plugin Slug (for folder name)

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

    # Ensure output dir exists (use absolute paths so .NET APIs resolve correctly)
    $outputDirAbs = Join-Path $root $outputDir
    if (-not (Test-Path $outputDirAbs)) {
        New-Item -ItemType Directory -Path $outputDirAbs | Out-Null
    }

    # Create a subfolder per release (version or timestamp)
    $releaseDir = Join-Path $outputDirAbs $releaseId
    if (-not (Test-Path $releaseDir)) { New-Item -ItemType Directory -Path $releaseDir | Out-Null }

    # Fixed zip name with Plugin Text Domain for unique WordPress identification
    $zipBaseName = "$pluginSlug.zip"
    $zipPath = Join-Path $releaseDir $zipBaseName

    Write-Host "Plugin Info:"
    Write-Host "  Text Domain: $pluginTextDomain"
    Write-Host "  Plugin Slug: $pluginSlug" 
    Write-Host "  Version: $releaseId"

    # Ensure any existing zip with same name is removed
    if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

    # Gather files to include - with better filtering and existence check
    $allFiles = Get-ChildItem -Path $root -Recurse -File -Force -ErrorAction SilentlyContinue | Where-Object {
        # exclude .git folder contents
        $_.FullName -notmatch "\\.git\\" -and
        # Make sure the file really exists and is readable
        (Test-Path $_.FullName -PathType Leaf) -and
        # Check that it's not a symlink or placeholder and not empty
        $_.Length -ne $null -and $_.Length -gt 0
    }

    $filesToInclude = @()
    foreach ($f in $allFiles) {
        # Additional existence check before processing
        if (-not (Test-Path $f.FullName -PathType Leaf)) { 
            Write-Warning "Skipping non-existent file: $($f.FullName)"
            continue 
        }
        
        # Skip empty files
        if ($f.Length -eq 0) {
            Write-Warning "Skipping empty file: $($f.FullName)"
            continue
        }
        
        if ($excludeNames -contains $f.Name) { continue }
        if ($excludeExtensions -contains $f.Extension.ToLower()) { continue }
        if ($f.FullName -match "\\\.git\\") { continue }
        # skip files inside output dir (corrected path check)
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
    # WordPress requires explicit folder entries in ZIP!
    if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
    
    # Create ZIP with .NET methods for better control over folder structure
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    Add-Type -AssemblyName System.IO.Compression
    
    $zipStream = [System.IO.File]::Create($zipPath)
    $zip = New-Object System.IO.Compression.ZipArchive($zipStream, [System.IO.Compression.ZipArchiveMode]::Create)
    
    try {
        # Collect all required folders from file paths
        $directories = @()
        foreach ($f in $filesToInclude) {
            $relative = $f.FullName.Substring($root.Length + 1)
            $dir = Split-Path $relative -Parent
            if ($dir -and $dir -ne '') {
                $directories += $dir
            }
        }
        
        # Remove duplicates and sort
        $uniqueDirs = $directories | Sort-Object | Get-Unique
        
        # Create explicit folder entries in ZIP (important for WordPress!)
        foreach ($dir in $uniqueDirs) {
            $dirEntry = $dir.Replace('\', '/') + '/'
            Write-Host "Creating directory entry: $dirEntry"
            $entry = $zip.CreateEntry($dirEntry)
            $entry.LastWriteTime = (Get-Date)
        }
        
        # Add all files
        foreach ($f in $filesToInclude) {
            if (-not (Test-Path $f.FullName -PathType Leaf)) {
                Write-Warning "Skipping file during ZIP creation - no longer exists: $($f.FullName)"
                continue
            }
            
            $relative = $f.FullName.Substring($root.Length + 1).Replace('\', '/')
            Write-Host "Adding file: $relative"
            
            # Create file entry
            $entry = $zip.CreateEntry($relative)
            $entry.LastWriteTime = $f.LastWriteTime
            
            # Copy file content
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
    Write-Host "Issue handling:"
    Write-Host "If you encounter issues with the plugin after upload, please follow these steps:"
    Write-Host "1. Deactivate the existing plugin in WordPress Admin"
    Write-Host "2. Delete the existing plugin completely (WordPress Admin > Plugins > Delete)"
    Write-Host "3. Upload $zipBaseName via WordPress Admin > Plugins > Add New > Upload Plugin"
    Write-Host "4. Activate the plugin"
    Write-Host ""
    Write-Host "IMPORTANT: Delete old plugin first to avoid cache conflicts!"

} catch {
    Write-Error $_.Exception.Message
} finally {
    Pop-Location
}
