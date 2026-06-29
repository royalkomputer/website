@echo off
setlocal enabledelayedexpansion
REM Royal Komputer - Push Admin Changes
REM Bat files called by PHP exec() runs as SYSTEM via Apache.
REM SYSTEM doesn't have cached git credentials, so we use GIT_TOKEN from .env.

set GIT=C:\Program Files\Git\cmd\git.exe
set BAT_DIR=%~dp0
set REPO_ROOT=%BAT_DIR%..

cd /d "%REPO_ROOT%"

REM ---- Read .env ----
set TOKEN=
if exist "%BAT_DIR%.env" (
    for /f "usebackq tokens=1,* delims==" %%a in ("%BAT_DIR%.env") do (
        if "%%a"=="GIT_TOKEN" set TOKEN=%%b
    )
)

REM ---- Get original remote URL ----
for /f "tokens=*" %%u in ('"%GIT%" remote get-url origin 2^>nul') do set REMOTE_URL=%%u
if "%REMOTE_URL%"=="" (
    echo [%date% %time%] ERROR: No git remote 'origin' found.
    exit /b 1
)

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

REM ---- Push with credentials ----
REM First try: if .env has GIT_TOKEN, build auth URL using PowerShell
if not "%TOKEN%"=="" (
    for /f "usebackq tokens=*" %%h in (`"%GIT%" remote get-url origin 2^>nul`) do set RAW_URL=%%h
    for /f "delims=" %%x in ('powershell -NoProfile -Command "Write-Output '%RAW_URL%' -replace '^https?://',''"') do set HOST_ONLY=%%x
    set AUTH_URL=https://x-access-token:%TOKEN%@!HOST_ONLY!
    "%GIT%" remote set-url origin !AUTH_URL!
    "%GIT%" push origin main
    set PUSH_EXIT=!errorlevel!
    "%GIT%" remote set-url origin "%REMOTE_URL%"
    if !PUSH_EXIT! equ 0 (
        echo [%date% %time%] Admin push complete.
        exit /b 0
    ) else (
        echo [%date% %time%] ERROR: Token push failed (exit code !PUSH_EXIT!).
        exit /b 1
    )
) else (
    REM No token - try default credentials
    "%GIT%" push origin main
    if %errorlevel% equ 0 (
        echo [%date% %time%] Admin push complete.
        exit /b 0
    ) else (
        echo [%date% %time%] ERROR: git push failed. Create a GitHub token and add to backend\.env
        exit /b 1
    )
)
