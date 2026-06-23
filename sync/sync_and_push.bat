@echo off
REM Royal Komputer - One-click sync + git push
REM Called by Windows Task Scheduler or run manually.

cd /d "%~dp0"

echo [%date% %time%] Starting sync...
php update_produk.php --once
if %errorlevel% neq 0 (
    echo [%date% %time%] WARNING: Sync exited with code %errorlevel%
)

echo [%date% %time%] Pushing to GitHub...
call git_push.bat

echo [%date% %time%] Done.
