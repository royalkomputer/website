@echo off
setlocal enabledelayedexpansion
REM Royal Komputer - Push Admin Changes

set GIT=C:\Program Files\Git\cmd\git.exe
set BAT_DIR=%~dp0
set REPO_ROOT=%BAT_DIR%..

cd /d "%REPO_ROOT%"

REM ---- Stage changes ----
"%GIT%" add -A
if %errorlevel% neq 0 (
    echo [%date% %time%] ERROR: git add failed.
    exit /b 1
)

REM ---- Check for changes ----
"%GIT%" diff --cached --quiet
if %errorlevel% equ 0 (
    echo [%date% %time%] No changes to commit.
    exit /b 0
)

REM ---- Commit ----
"%GIT%" commit -m "admin: update %date% %time%"
if %errorlevel% neq 0 (
    echo [%date% %time%] ERROR: git commit failed.
    exit /b 1
)

REM ---- Push (uses default credentials - works when run as logged-in user) ----
"%GIT%" push origin main
if %errorlevel% equ 0 (
    echo [%date% %time%] Admin push complete.
    exit /b 0
) else (
    echo [%date% %time%] ERROR: git push failed.
    exit /b 1
)
