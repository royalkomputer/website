@echo off
REM Royal Komputer - Sync Agent Git Push
REM 
REM Two modes:
REM   1. Task Scheduler: runs standalone after sync/update_produk.php
REM   2. Integrated: use "php update_produk.php --watch --git-push" for auto push
REM
REM This batch is called by Windows Task Scheduler.

cd /d "%~dp0.."

REM Add all changes
git add -A

REM Check if there are changes to commit
git diff --cached --quiet
if %errorlevel% equ 0 (
    echo [%date% %time%] No changes to commit.
    exit /b 0
)

REM Commit and push
git commit -m "sync: product data update %date% %time%"
git push origin main

echo [%date% %time%] Sync complete.
