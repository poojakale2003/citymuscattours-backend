# Database Import Script for WAMP
Write-Host "=== Database Import Script ===" -ForegroundColor Cyan
Write-Host ""

$mysqlPath = "C:\wamp64\bin\mysql\mysql*\bin\mysql.exe"
$mysqlExe = Get-ChildItem -Path "C:\wamp64\bin\mysql" -Filter "mysql.exe" -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty FullName

if (-not $mysqlExe) {
    Write-Host "ERROR: MySQL not found in WAMP. Please ensure WAMP MySQL is running." -ForegroundColor Red
    Write-Host ""
    Write-Host "Alternative: Use phpMyAdmin to import database/schema.sql" -ForegroundColor Yellow
    Write-Host "1. Open: http://localhost/phpmyadmin" -ForegroundColor White
    Write-Host "2. Select 'tour_travels' database" -ForegroundColor White
    Write-Host "3. Click 'Import' tab" -ForegroundColor White
    Write-Host "4. Choose 'database/schema.sql' file" -ForegroundColor White
    Write-Host "5. Click 'Go'" -ForegroundColor White
    exit 1
}

Write-Host "Found MySQL: $mysqlExe" -ForegroundColor Green
Write-Host ""

$schemaFile = Join-Path $PSScriptRoot "database\schema.sql"
if (-not (Test-Path $schemaFile)) {
    Write-Host "ERROR: Schema file not found: $schemaFile" -ForegroundColor Red
    exit 1
}

Write-Host "Importing database schema..." -ForegroundColor Yellow
Write-Host "Note: This will create the database if it doesn't exist" -ForegroundColor Gray
Write-Host ""

# Try to import (will prompt for password if needed)
try {
    & $mysqlExe -u root -p < $schemaFile
    Write-Host ""
    Write-Host "[OK] Database schema imported successfully!" -ForegroundColor Green
} catch {
    Write-Host ""
    Write-Host "If password prompt appeared, enter your MySQL root password" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Alternative method using phpMyAdmin:" -ForegroundColor Cyan
    Write-Host "1. Open: http://localhost/phpmyadmin" -ForegroundColor White
    Write-Host "2. Create database 'tour_travels' if it doesn't exist" -ForegroundColor White
    Write-Host "3. Select 'tour_travels' database" -ForegroundColor White
    Write-Host "4. Click 'Import' tab" -ForegroundColor White
    Write-Host "5. Choose file: $schemaFile" -ForegroundColor White
    Write-Host "6. Click 'Go'" -ForegroundColor White
}

Write-Host ""
Write-Host "After importing, test the connection:" -ForegroundColor Cyan
Write-Host "  C:\wamp64\bin\php\php8.1.27\php.exe test-db-connection.php" -ForegroundColor White
Write-Host ""

