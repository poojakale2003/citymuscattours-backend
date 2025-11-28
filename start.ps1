# Quick Start Script for PHP Backend
Write-Host "=== Starting PHP Backend ===" -ForegroundColor Cyan
Write-Host ""

$projectPath = "C:\wamp64\www\php-backend"
$phpPath = "C:\wamp64\bin\php\php8.1.27\php.exe"

# Check if vendor exists
$vendorExists = Test-Path (Join-Path $projectPath "vendor")
if (-not $vendorExists) {
    Write-Host "WARNING: Dependencies not installed!" -ForegroundColor Yellow
    Write-Host "Please install Composer dependencies first:" -ForegroundColor Yellow
    Write-Host "  1. Install Composer from https://getcomposer.org/download/" -ForegroundColor White
    Write-Host "  2. Run: composer install" -ForegroundColor White
    Write-Host ""
    Write-Host "Trying to start anyway..." -ForegroundColor Yellow
    Write-Host ""
}

# Check if .env exists
$envExists = Test-Path (Join-Path $projectPath ".env")
if (-not $envExists) {
    Write-Host "WARNING: .env file not found!" -ForegroundColor Yellow
    Write-Host "Run setup.ps1 first to create .env file" -ForegroundColor Yellow
    Write-Host ""
}

Write-Host "Starting PHP built-in server..." -ForegroundColor Green
Write-Host "Access the application at: http://localhost:8000" -ForegroundColor Cyan
Write-Host "Health check: http://localhost:8000/health" -ForegroundColor Cyan
Write-Host ""
Write-Host "Press Ctrl+C to stop the server" -ForegroundColor Yellow
Write-Host ""

# Change to project directory and start server
Set-Location $projectPath
& $phpPath -S localhost:8000

