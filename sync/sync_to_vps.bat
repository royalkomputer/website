@echo off
REM Royal Komputer - Direct sync to VPS (rsync via SSH)
REM Called by Windows Task Scheduler every 1 hour.
REM Replaces the old git_push flow — syncs directly to VPS.

cd /d "%~dp0"

set PHP_PATH=C:\xampp\php\php.exe

echo [%date% %time%] Starting sync...
"%PHP_PATH%" update_produk.php --once
if %errorlevel% neq 0 (
    echo [%date% %time%] WARNING: Sync exited with code %errorlevel%
)

echo [%date% %time%] Done.