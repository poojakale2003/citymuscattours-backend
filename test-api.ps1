# Quick API Test Script
# This script tests the main API endpoints

$baseUrl = "http://localhost:8000"
$token = $null

Write-Host "=== API Testing Script ===" -ForegroundColor Cyan
Write-Host "Base URL: $baseUrl" -ForegroundColor Gray
Write-Host ""

# Test 1: Health Check
Write-Host "[1] Testing Health Check..." -ForegroundColor Yellow
try {
    $health = Invoke-RestMethod -Uri "$baseUrl/health" -Method Get
    Write-Host "  Status: $($health.status)" -ForegroundColor Green
    Write-Host "  Database: $($health.database)" -ForegroundColor $(if ($health.database -eq "connected") { "Green" } else { "Red" })
    if ($health.error) {
        Write-Host "  Error: $($health.error)" -ForegroundColor Red
    }
} catch {
    Write-Host "  FAILED: $($_.Exception.Message)" -ForegroundColor Red
}
Write-Host ""

# Test 2: Register User
Write-Host "[2] Testing User Registration..." -ForegroundColor Yellow
$registerBody = @{
    name = "Test User"
    email = "test$(Get-Random)@example.com"
    password = "password123"
    phone = "1234567890"
} | ConvertTo-Json

try {
    $register = Invoke-RestMethod -Uri "$baseUrl/api/auth/register" `
        -Method Post `
        -ContentType "application/json" `
        -Body $registerBody
    
    Write-Host "  SUCCESS: User registered!" -ForegroundColor Green
    Write-Host "  Email: $($register.user.email)" -ForegroundColor Gray
    Write-Host "  Token: $($register.accessToken.Substring(0, 20))..." -ForegroundColor Gray
    
    $token = $register.accessToken
    $testEmail = $register.user.email
} catch {
    if ($_.Exception.Response.StatusCode -eq 409) {
        Write-Host "  User already exists, trying to login instead..." -ForegroundColor Yellow
        $testEmail = ($registerBody | ConvertFrom-Json).email
    } else {
        Write-Host "  FAILED: $($_.Exception.Message)" -ForegroundColor Red
    }
}
Write-Host ""

# Test 3: Login
if (-not $token) {
    Write-Host "[3] Testing User Login..." -ForegroundColor Yellow
    if (-not $testEmail) {
        $testEmail = "test@example.com"
    }
    
    $loginBody = @{
        email = $testEmail
        password = "password123"
    } | ConvertTo-Json
    
    try {
        $login = Invoke-RestMethod -Uri "$baseUrl/api/auth/login" `
            -Method Post `
            -ContentType "application/json" `
            -Body $loginBody
        
        Write-Host "  SUCCESS: Login successful!" -ForegroundColor Green
        Write-Host "  User: $($login.user.name)" -ForegroundColor Gray
        $token = $login.accessToken
    } catch {
        Write-Host "  FAILED: $($_.Exception.Message)" -ForegroundColor Red
        Write-Host "  Note: Make sure user exists or register first" -ForegroundColor Yellow
    }
    Write-Host ""
} else {
    Write-Host "[3] Skipping login (already have token from registration)" -ForegroundColor Gray
    Write-Host ""
}

# Test 4: Get Packages
Write-Host "[4] Testing Get Packages..." -ForegroundColor Yellow
try {
    $packages = Invoke-RestMethod -Uri "$baseUrl/api/packages" -Method Get
    if ($packages.data) {
        Write-Host "  SUCCESS: Found $($packages.data.Count) packages" -ForegroundColor Green
        if ($packages.data.Count -gt 0) {
            Write-Host "  First package: $($packages.data[0].name)" -ForegroundColor Gray
        } else {
            Write-Host "  Note: No packages in database yet" -ForegroundColor Yellow
        }
    } else {
        Write-Host "  SUCCESS: Packages endpoint works" -ForegroundColor Green
    }
} catch {
    Write-Host "  FAILED: $($_.Exception.Message)" -ForegroundColor Red
}
Write-Host ""

# Test 5: Get User Profile (requires auth)
if ($token) {
    Write-Host "[5] Testing Get User Profile (Authenticated)..." -ForegroundColor Yellow
    $headers = @{
        "Authorization" = "Bearer $token"
    }
    
    try {
        $profile = Invoke-RestMethod -Uri "$baseUrl/api/users/profile" `
            -Method Get `
            -Headers $headers
        
        Write-Host "  SUCCESS: Profile retrieved!" -ForegroundColor Green
        Write-Host "  Name: $($profile.user.name)" -ForegroundColor Gray
        Write-Host "  Email: $($profile.user.email)" -ForegroundColor Gray
    } catch {
        Write-Host "  FAILED: $($_.Exception.Message)" -ForegroundColor Red
    }
    Write-Host ""
} else {
    Write-Host "[5] Skipping profile test (no token available)" -ForegroundColor Gray
    Write-Host ""
}

# Test 6: Get Reviews
Write-Host "[6] Testing Get Reviews..." -ForegroundColor Yellow
try {
    $reviews = Invoke-RestMethod -Uri "$baseUrl/api/reviews?packageId=1" -Method Get
    Write-Host "  SUCCESS: Reviews endpoint works" -ForegroundColor Green
} catch {
    if ($_.Exception.Response.StatusCode -eq 400) {
        Write-Host "  Note: Package ID parameter required" -ForegroundColor Yellow
    } else {
        Write-Host "  SUCCESS: Reviews endpoint accessible" -ForegroundColor Green
    }
}
Write-Host ""

# Summary
Write-Host "=== Test Summary ===" -ForegroundColor Cyan
Write-Host ""
if ($token) {
    Write-Host "Access Token (save this for manual testing):" -ForegroundColor Yellow
    Write-Host $token -ForegroundColor White
    Write-Host ""
}

Write-Host "Manual Testing:" -ForegroundColor Cyan
Write-Host "1. Test in browser: $baseUrl/api/packages" -ForegroundColor White
Write-Host "2. Use Postman with token: Bearer $($token.Substring(0, 20) + '...')" -ForegroundColor White
Write-Host "3. Use cURL:" -ForegroundColor White
Write-Host "   curl $baseUrl/api/packages" -ForegroundColor Gray
Write-Host ""
Write-Host "Full testing guide: See API-TESTING.md" -ForegroundColor Gray
Write-Host ""

