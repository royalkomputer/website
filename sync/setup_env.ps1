# Royal Komputer — PHP Environment Setup
# Run this once to add PHP to your PATH (Windows 11).
# Open a NEW PowerShell as admin, then: powershell -ExecutionPolicy Bypass -File sync\setup_env.ps1

$phpPath = "C:\Users\DESKTOP\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe"

if (-not (Test-Path "$phpPath\php.exe")) {
    Write-Host "PHP not found at: $phpPath" -ForegroundColor Red
    Write-Host "Install PHP first: winget install PHP.PHP.8.4" -ForegroundColor Yellow
    exit 1
}

# Add to User PATH (persistent)
$userPath = [Environment]::GetEnvironmentVariable('Path', 'User')
if ($userPath -like "*$phpPath*") {
    Write-Host "PHP is already in PATH." -ForegroundColor Green
} else {
    [Environment]::SetEnvironmentVariable('Path', "$userPath;$phpPath", 'User')
    Write-Host "Added PHP to User PATH." -ForegroundColor Green
}

# Also add for the current session
$env:Path = $userPath + ';' + [Environment]::GetEnvironmentVariable('Path', 'Machine')

php --version
