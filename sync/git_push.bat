@echo off
REM Royal Komputer - Sync Agent Git Push
REM Called by Windows Task Scheduler after sync/update_produk.php runs

cd /d "%~dp0.."

REM Add all changes
git add -A

REM Check if there are changes to commit
git diff --cached --quiet
if %errorlevel% equ 0 (
    echo No changes to commit.
    exit /b 0
)

REM Commit and push
git commit -m "sync: product data update %date% %time%"
git push origin main

echo Sync complete.
