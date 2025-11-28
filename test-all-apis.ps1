# Test All APIs
# PowerShell script to test all API endpoints

Write-Host "=== API Testing Script ===" -ForegroundColor Cyan
Write-Host ""

$BASE_URL = "http://localhost:8000"

# Check if server is running
Write-Host "Checking if server is running..." -ForegroundColor Yellow
try {
    $response = Invoke-RestMethod -Uri "$BASE_URL/health" -Method Get -TimeoutSec 5 -ErrorAction Stop
    Write-Host "✓ Server is running" -ForegroundColor Green
    Write-Host ""
} catch {
    Write-Host "✗ Server is not running!" -ForegroundColor Red
    Write-Host "Please start the server first:" -ForegroundColor Yellow
    Write-Host "  .\start.ps1" -ForegroundColor Gray
    Write-Host "  OR" -ForegroundColor Gray
    Write-Host "  php -S localhost:8000" -ForegroundColor Gray
    exit 1
}

# Test Health Check
Write-Host "1. Testing Health Check..." -ForegroundColor Cyan
try {
    $response = Invoke-RestMethod -Uri "$BASE_URL/health" -Method Get
    Write-Host "  ✓ Health Check: OK" -ForegroundColor Green
    Write-Host "    Database: $($response.database)" -ForegroundColor Gray
} catch {
    Write-Host "  ✗ Health Check: FAILED" -ForegroundColor Red
    Write-Host "    Error: $_" -ForegroundColor Gray
}

Write-Host ""
Write-Host "2. Testing Authentication..." -ForegroundColor Cyan

# Test Login
$testEmail = "admin@example.com"
$testPassword = "admin123"

Write-Host "  Testing Login..." -ForegroundColor Yellow
try {
    $loginBody = @{
        email = $testEmail
        password = $testPassword
    } | ConvertTo-Json

    $loginResponse = Invoke-RestMethod -Uri "$BASE_URL/api/auth/login" -Method Post -Body $loginBody -ContentType "application/json"
    
    if ($loginResponse.tokens.accessToken -or $loginResponse.accessToken) {
        if ($loginResponse.tokens.accessToken) {
            $accessToken = $loginResponse.tokens.accessToken
            $refreshToken = $loginResponse.tokens.refreshToken
        } else {
            $accessToken = $loginResponse.accessToken
            $refreshToken = $loginResponse.refreshToken
        }
        Write-Host "  ✓ Login: SUCCESS" -ForegroundColor Green
        Write-Host "    User: $($loginResponse.user.name)" -ForegroundColor Gray
        Write-Host "    Role: $($loginResponse.user.role)" -ForegroundColor Gray
    } else {
        Write-Host "  ✗ Login: FAILED - No token received" -ForegroundColor Red
        $accessToken = $null
        $refreshToken = $null
    }
} catch {
    Write-Host "  ✗ Login: FAILED" -ForegroundColor Red
    Write-Host "    Error: $_" -ForegroundColor Gray
    $accessToken = $null
    $refreshToken = $null
}

Write-Host ""
Write-Host "3. Testing Packages..." -ForegroundColor Cyan

# Test List Packages
Write-Host "  Testing List Packages..." -ForegroundColor Yellow
try {
    $packages = Invoke-RestMethod -Uri "$BASE_URL/api/packages" -Method Get
    Write-Host "  ✓ List Packages: SUCCESS" -ForegroundColor Green
    Write-Host "    Found $($packages.meta.total) packages" -ForegroundColor Gray
} catch {
    Write-Host "  ✗ List Packages: FAILED" -ForegroundColor Red
    Write-Host "    Error: $_" -ForegroundColor Gray
}

# Test Create Package (if admin token available)
if ($accessToken) {
    Write-Host ""
    Write-Host "  Testing Create Package (Admin)..." -ForegroundColor Yellow
    try {
        $packageData = @{
            title = "Test Package $(Get-Date -Format 'yyyyMMddHHmmss')"
            destination = "Test Destination"
            price = 1999.99
        } | ConvertTo-Json

        $headers = @{
            "Authorization" = "Bearer $accessToken"
            "Content-Type" = "application/json"
        }

        $createResponse = Invoke-RestMethod -Uri "$BASE_URL/api/packages" -Method Post -Body $packageData -Headers $headers -ContentType "application/json"
        Write-Host "  ✓ Create Package: SUCCESS" -ForegroundColor Green
        Write-Host "    Package ID: $($createResponse.data.id)" -ForegroundColor Gray
        $createdPackageId = $createResponse.data.id
    } catch {
        Write-Host "  ✗ Create Package: FAILED" -ForegroundColor Red
        Write-Host "    Error: $_" -ForegroundColor Gray
        Write-Host "    Note: This might fail if user is not admin" -ForegroundColor Gray
        $createdPackageId = $null
    }
}

Write-Host ""
Write-Host "4. Testing User Profile..." -ForegroundColor Cyan

if ($accessToken) {
    Write-Host "  Testing Get User Profile..." -ForegroundColor Yellow
    try {
        $headers = @{
            "Authorization" = "Bearer $accessToken"
        }
        $profile = Invoke-RestMethod -Uri "$BASE_URL/api/users/profile" -Method Get -Headers $headers
        Write-Host "  ✓ Get User Profile: SUCCESS" -ForegroundColor Green
        Write-Host "    Name: $($profile.data.name)" -ForegroundColor Gray
        Write-Host "    Email: $($profile.data.email)" -ForegroundColor Gray
    } catch {
        Write-Host "  ✗ Get User Profile: FAILED" -ForegroundColor Red
        Write-Host "    Error: $_" -ForegroundColor Gray
    }
} else {
    Write-Host "  ⊘ Get User Profile: SKIPPED (No token)" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "5. Testing Token Refresh..." -ForegroundColor Cyan

if ($refreshToken) {
    Write-Host "  Testing Refresh Token..." -ForegroundColor Yellow
    try {
        $refreshBody = @{
            refreshToken = $refreshToken
        } | ConvertTo-Json

        $refreshResponse = Invoke-RestMethod -Uri "$BASE_URL/api/auth/refresh" -Method Post -Body $refreshBody -ContentType "application/json"
        Write-Host "  ✓ Refresh Token: SUCCESS" -ForegroundColor Green
        Write-Host "    New tokens received" -ForegroundColor Gray
    } catch {
        Write-Host "  ✗ Refresh Token: FAILED" -ForegroundColor Red
        Write-Host "    Error: $_" -ForegroundColor Gray
    }
} else {
    Write-Host "  ⊘ Refresh Token: SKIPPED (No refresh token)" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "=== Test Complete ===" -ForegroundColor Green
Write-Host ""
Write-Host "For detailed testing, run:" -ForegroundColor Cyan
Write-Host "  php test-all-apis.php" -ForegroundColor Gray
Write-Host ""
Write-Host "Or visit:" -ForegroundColor Cyan
Write-Host "  http://localhost:8000/test-all-apis.php" -ForegroundColor Gray
Write-Host ""

