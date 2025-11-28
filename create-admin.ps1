# PowerShell script to create admin user
# This script helps you create an admin user via the PHP script

Write-Host "=== Create Admin User ===" -ForegroundColor Cyan
Write-Host ""

$phpPath = "C:\wamp64\bin\php\php8.1.27\php.exe"

# Check if PHP exists
if (-not (Test-Path $phpPath)) {
    Write-Host "ERROR: PHP not found at $phpPath" -ForegroundColor Red
    Write-Host "Please update the PHP path in this script" -ForegroundColor Yellow
    exit 1
}

# Get admin details
Write-Host "Enter admin user details (press Enter for defaults):" -ForegroundColor Yellow
Write-Host ""

$name = Read-Host "Name [Admin User]"
if ([string]::IsNullOrWhiteSpace($name)) {
    $name = "Admin User"
}

$email = Read-Host "Email [admin@example.com]"
if ([string]::IsNullOrWhiteSpace($email)) {
    $email = "admin@example.com"
}

$password = Read-Host "Password [admin123]" -AsSecureString
if ($password) {
    $BSTR = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($password)
    $password = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto($BSTR)
} else {
    $password = "admin123"
}

Write-Host ""
Write-Host "Creating admin user..." -ForegroundColor Yellow
Write-Host "  Name: $name" -ForegroundColor Gray
Write-Host "  Email: $email" -ForegroundColor Gray
Write-Host ""

# Run PHP script
& $phpPath create-admin.php $name $email $password

Write-Host ""
Write-Host "=== Done ===" -ForegroundColor Green
Write-Host ""
Write-Host "You can now login with:" -ForegroundColor Cyan
Write-Host "  Email: $email" -ForegroundColor White
Write-Host "  Password: $password" -ForegroundColor White
Write-Host ""

