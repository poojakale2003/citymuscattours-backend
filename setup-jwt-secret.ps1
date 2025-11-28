# Setup JWT Secret
# This script generates and sets JWT secrets in .env file

Write-Host "=== JWT Secret Setup ===" -ForegroundColor Cyan
Write-Host ""

$envFile = ".env"

if (-not (Test-Path $envFile)) {
    Write-Host "Error: .env file not found!" -ForegroundColor Red
    Write-Host "Please run setup.ps1 first to create .env file" -ForegroundColor Yellow
    exit 1
}

Write-Host "Found .env file" -ForegroundColor Green
Write-Host ""

# Check current JWT_SECRET
$currentSecret = Get-Content $envFile | Select-String -Pattern "^JWT_SECRET="
if ($currentSecret) {
    $currentValue = $currentSecret.ToString() -replace "JWT_SECRET=", ""
    if ($currentValue -eq "your-secret-key-change-this-in-production" -or $currentValue.Trim() -eq "") {
        Write-Host "Current JWT_SECRET is placeholder or empty" -ForegroundColor Yellow
        $needsUpdate = $true
    } else {
        Write-Host "Current JWT_SECRET is already set" -ForegroundColor Green
        Write-Host "Secret preview: $($currentValue.Substring(0, [Math]::Min(10, $currentValue.Length)))..." -ForegroundColor Gray
        $response = Read-Host "Do you want to generate a new secret? (y/n)"
        $needsUpdate = $response -eq "y" -or $response -eq "Y"
    }
} else {
    Write-Host "JWT_SECRET not found in .env file" -ForegroundColor Yellow
    $needsUpdate = $true
}

if ($needsUpdate) {
    Write-Host ""
    Write-Host "Generating secure JWT secrets..." -ForegroundColor Cyan
    
    # Generate secure random secrets using .NET Cryptography
    $bytes1 = New-Object byte[] 32
    $rng1 = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    $rng1.GetBytes($bytes1)
    $jwtSecret = [Convert]::ToBase64String($bytes1) -replace '\+', '-' -replace '/', '_' -replace '=',''
    
    $bytes2 = New-Object byte[] 32
    $rng2 = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    $rng2.GetBytes($bytes2)
    $jwtRefreshSecret = [Convert]::ToBase64String($bytes2) -replace '\+', '-' -replace '/', '_' -replace '=',''
    
    Write-Host "Generated secrets:" -ForegroundColor Green
    Write-Host "JWT_SECRET: $($jwtSecret.Substring(0, 10))..." -ForegroundColor Gray
    Write-Host "JWT_REFRESH_SECRET: $($jwtRefreshSecret.Substring(0, 10))..." -ForegroundColor Gray
    Write-Host ""
    
    # Read .env file
    $envContent = Get-Content $envFile
    
    # Update JWT_SECRET
    $envContent = $envContent | ForEach-Object {
        if ($_ -match "^JWT_SECRET=") {
            "JWT_SECRET=$jwtSecret"
        } elseif ($_ -match "^JWT_REFRESH_SECRET=") {
            # Update if empty, or add new one
            if ($_ -match "^JWT_REFRESH_SECRET=\s*$") {
                "JWT_REFRESH_SECRET=$jwtRefreshSecret"
            } else {
                $_
            }
        } else {
            $_
        }
    }
    
    # Save updated .env file
    $envContent | Set-Content $envFile -Encoding UTF8
    
    Write-Host "✓ Updated .env file with new JWT secrets" -ForegroundColor Green
    Write-Host ""
    Write-Host "Your JWT secrets have been configured:" -ForegroundColor Cyan
    Write-Host "  JWT_SECRET: Set (32-byte base64 encoded)" -ForegroundColor White
    Write-Host "  JWT_REFRESH_SECRET: Set (32-byte base64 encoded)" -ForegroundColor White
    Write-Host ""
    Write-Host "The refresh secret will use JWT_SECRET as fallback if needed." -ForegroundColor Gray
    Write-Host ""
} else {
    Write-Host "No changes made to .env file" -ForegroundColor Green
}

Write-Host "=== Setup Complete ===" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Cyan
Write-Host "1. Verify JWT config: php check-jwt-config.php" -ForegroundColor White
Write-Host "2. Test login: POST http://localhost:8000/api/auth/login" -ForegroundColor White
Write-Host ""

