@echo off
title Convert to WebP — Royal Komputer
echo ============================================
echo  Convert Images to WebP (60%% quality)
echo  Folder: %~dp0uploads\
echo ============================================
echo.

REM ── PHP PATH — edit this if needed ──
set PHP="C:\Users\DESKTOP\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
REM ─────────────────────────────────────

%PHP% "%~dp0convert_to_webp.php"

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo PHP not found.
    echo Edit the PHP path at the top of this script.
    pause
    exit /b 1
)

pause
