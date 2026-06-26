@echo off
REM Royal Komputer - Push Admin Changes
REM Run this after making admin panel changes (config, data, PHP files).

cd /d "%~dp0.."

git add -A

git diff --cached --quiet
if %errorlevel% equ 0 (
    echo [%date% %time%] No changes to commit.
    exit /b 0
)

git commit -m "admin: update %date% %time%"
git push origin main

echo [%date% %time%] Admin push complete.
