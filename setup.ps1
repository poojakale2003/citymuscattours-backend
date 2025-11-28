# PHP Backend Setup Script for Windows/WAMP
Write-Host "=== PHP Backend Setup ===" -ForegroundColor Cyan
Write-Host ""

# Check WAMP PHP path
$phpPath = "C:\wamp64\bin\php\php8.1.27\php.exe"
if (-not (Test-Path $phpPath)) {
    # Try other PHP versions
    $phpVersions = @("php8.2.0", "php8.1.13", "php8.0.26", "php7.4.33")
    foreach ($version in $phpVersions) {
        $testPath = "C:\wamp64\bin\php\$version\php.exe"
        if (Test-Path $testPath) {
            $phpPath = $testPath
            break
        }
    }
}

if (-not (Test-Path $phpPath)) {
    Write-Host "ERROR: PHP not found in WAMP. Please ensure WAMP is installed." -ForegroundColor Red
    exit 1
}

Write-Host "Using PHP: $phpPath" -ForegroundColor Green
& $phpPath --version
Write-Host ""

# Check if composer is available
$composer = "composer"
$composerPhar = Join-Path $PSScriptRoot "composer.phar"
$composerGlobal = "$env:APPDATA\Composer\vendor\bin\composer.bat"

if (Get-Command composer -ErrorAction SilentlyContinue) {
    $composer = "composer"
} elseif (Test-Path $composerGlobal) {
    $composer = $composerGlobal
} elseif (Test-Path $composerPhar) {
    $composer = "$phpPath $composerPhar"
} else {
    Write-Host "WARNING: Composer not found. Installing dependencies manually..." -ForegroundColor Yellow
    Write-Host "Please install Composer from https://getcomposer.org/download/" -ForegroundColor Yellow
    Write-Host ""
    
    # Create .env file if it doesn't exist
    $envFile = Join-Path $PSScriptRoot ".env"
    if (-not (Test-Path $envFile)) {
        Write-Host "Creating .env file..." -ForegroundColor Yellow
        @"
# Server Configuration
APP_ENV=development
PORT=5000
CLIENT_URL=http://localhost:3000

# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_NAME=tour_travels
DB_USER=root
DB_PASS=

# JWT Configuration
JWT_SECRET=your-secret-key-change-this-in-production
JWT_REFRESH_SECRET=
JWT_EXPIRY=24h
JWT_REFRESH_EXPIRY=30d

# Email Configuration (Optional)
EMAIL_FROM=no-reply@travelapp.com
EMAIL_HOST=
EMAIL_PORT=587
EMAIL_USER=
EMAIL_PASS=

# Payment Gateway Configuration (Optional)
RAZORPAY_KEY_ID=
RAZORPAY_KEY_SECRET=
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
"@ | Out-File -FilePath $envFile -Encoding utf8
        Write-Host "[OK] .env file created. Please update database credentials." -ForegroundColor Green
    } else {
        Write-Host "[OK] .env file already exists" -ForegroundColor Green
    }
    
    Write-Host ""
    Write-Host "Next steps:" -ForegroundColor Cyan
    Write-Host "1. Install Composer: https://getcomposer.org/download/" -ForegroundColor White
    Write-Host "2. Run: composer install" -ForegroundColor White
    Write-Host "3. Update .env file with your database credentials" -ForegroundColor White
    Write-Host "4. Import database schema from database/schema.sql" -ForegroundColor White
    Write-Host "5. Access via: http://localhost/php-backend/health" -ForegroundColor White
    exit 0
}

# Install dependencies
Write-Host "Installing Composer dependencies..." -ForegroundColor Yellow
Push-Location $PSScriptRoot
try {
    if ($composer -eq "composer" -or (Test-Path $composerGlobal)) {
        & $composer install --no-interaction
    } else {
        & $phpPath $composerPhar install --no-interaction
    }
    Write-Host "[OK] Dependencies installed" -ForegroundColor Green
} catch {
    Write-Host "ERROR: Failed to install dependencies" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
}
Pop-Location

# Create .env file if it doesn't exist
$envFile = Join-Path $PSScriptRoot ".env"
if (-not (Test-Path $envFile)) {
    Write-Host ""
    Write-Host "Creating .env file..." -ForegroundColor Yellow
    @"
# Server Configuration
APP_ENV=development
PORT=5000
CLIENT_URL=http://localhost:3000

# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_NAME=tour_travels
DB_USER=root
DB_PASS=

# JWT Configuration
JWT_SECRET=your-secret-key-change-this-in-production
JWT_REFRESH_SECRET=
JWT_EXPIRY=24h
JWT_REFRESH_EXPIRY=30d

# Email Configuration (Optional)
EMAIL_FROM=no-reply@travelapp.com
EMAIL_HOST=
EMAIL_PORT=587
EMAIL_USER=
EMAIL_PASS=

# Payment Gateway Configuration (Optional)
RAZORPAY_KEY_ID=
RAZORPAY_KEY_SECRET=
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
"@ | Out-File -FilePath $envFile -Encoding utf8
    Write-Host "[OK] .env file created. Please update database credentials." -ForegroundColor Green
} else {
    Write-Host "[OK] .env file already exists" -ForegroundColor Green
}

Write-Host ""
Write-Host "=== Setup Complete ===" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Cyan
Write-Host "1. Update .env file with your database credentials" -ForegroundColor White
    Write-Host "2. Import database schema: mysql -u root -p tour_travels < database/schema.sql" -ForegroundColor White -NoNewline
    Write-Host " (or use phpMyAdmin)" -ForegroundColor Gray
Write-Host "3. Test connection: php test-db-connection.php" -ForegroundColor White
Write-Host "4. Access via WAMP: http://localhost/php-backend/health" -ForegroundColor White
Write-Host ""

