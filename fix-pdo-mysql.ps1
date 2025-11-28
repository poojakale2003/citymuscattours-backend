# Fix PDO MySQL Extension for WAMP
Write-Host "=== Fixing PDO MySQL Extension ===" -ForegroundColor Cyan
Write-Host ""

# Find all php.ini files in WAMP
$phpIniFiles = @()

# Check Apache php.ini
$apacheIni = Get-ChildItem -Path "C:\wamp64\bin\apache" -Filter "php.ini" -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1
if ($apacheIni) {
    $phpIniFiles += $apacheIni.FullName
    Write-Host "Found Apache php.ini: $($apacheIni.FullName)" -ForegroundColor Green
}

# Check PHP phpForApache.ini
$phpForApache = "C:\wamp64\bin\php\php8.1.27\phpForApache.ini"
if (Test-Path $phpForApache) {
    $phpIniFiles += $phpForApache
    Write-Host "Found phpForApache.ini: $phpForApache" -ForegroundColor Green
}

# Check CLI php.ini (for reference)
$cliIni = "C:\wamp64\bin\php\php8.1.27\php.ini"
if (Test-Path $cliIni) {
    Write-Host "Found CLI php.ini: $cliIni" -ForegroundColor Gray
}

Write-Host ""

foreach ($iniFile in $phpIniFiles) {
    Write-Host "Processing: $iniFile" -ForegroundColor Yellow
    
    $content = Get-Content $iniFile -Raw
    $modified = $false
    
    # Check if pdo_mysql is commented out
    if ($content -match ';extension=pdo_mysql') {
        $content = $content -replace ';extension=pdo_mysql', 'extension=pdo_mysql'
        $modified = $true
        Write-Host "  [OK] Enabled pdo_mysql (was commented)" -ForegroundColor Green
    }
    # Check if it's already enabled
    elseif ($content -match '^extension=pdo_mysql' -or $content -match "`nextension=pdo_mysql") {
        Write-Host "  [OK] pdo_mysql already enabled" -ForegroundColor Green
    }
    # If not found, add it
    else {
        # Find extension_dir to add after it
        if ($content -match '(?m)^extension_dir\s*=') {
            $content = $content -replace '(?m)(^extension_dir\s*=.*)', "`$1`nextension=pdo_mysql"
            $modified = $true
            Write-Host "  [OK] Added pdo_mysql extension" -ForegroundColor Green
        } else {
            # Add at end of file
            $content += "`nextension=pdo_mysql`n"
            $modified = $true
            Write-Host "  [OK] Added pdo_mysql extension at end of file" -ForegroundColor Green
        }
    }
    
    if ($modified) {
        Set-Content -Path $iniFile -Value $content -NoNewline
        Write-Host "  [OK] File saved" -ForegroundColor Green
    }
    
    Write-Host ""
}

Write-Host "=== Fix Complete ===" -ForegroundColor Green
Write-Host ""
Write-Host "IMPORTANT: You must restart Apache for changes to take effect!" -ForegroundColor Yellow
Write-Host ""
Write-Host "To restart Apache:" -ForegroundColor Cyan
Write-Host "  1. Click WAMP icon in system tray" -ForegroundColor White
Write-Host "  2. Click 'Restart All Services'" -ForegroundColor White
Write-Host "  OR" -ForegroundColor White
Write-Host "  3. Click 'Apache' > 'Service' > 'Restart Service'" -ForegroundColor White
Write-Host ""
Write-Host "After restarting, test again:" -ForegroundColor Cyan
Write-Host "  http://localhost/php-backend/health" -ForegroundColor White
Write-Host ""

