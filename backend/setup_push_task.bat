@echo off
setlocal
REM Royal Komputer - Setup Admin Push Scheduled Task
REM Run this once as Administrator to create the scheduled task
REM that runs push_admin.bat as the currently logged-in user.

echo ======================================================
echo   Royal Komputer - Admin Push Task Setup
echo ======================================================
echo.
echo This script creates a scheduled task "RoyalKomputer Admin Push"
echo that runs push_admin.bat as the currently logged-in user.
echo.
echo The task is needed because the admin panel runs as SYSTEM
echo (via Apache), which doesn't have git credentials.
echo.
echo The task uses the same user as "RoyalKomputer Sync".
echo.

REM Get the user from the existing sync task
for /f "skip=2 tokens=1,* delims=," %%a in ('schtasks /Query /FO CSV /TN "RoyalKomputer Sync" /V 2^>nul') do (
    set TASK_USER=%%b
)
REM The user is in a CSV format, clean it up
REM Actually, let's try a simpler approach - get the user from query

REM Find the console session user
for /f "tokens=2" %%u in ('query user 2^>nul') do (
    set CONSOLE_USER=%%u
    goto :found_user
)
if "%CONSOLE_USER%"=="" (
    echo ERROR: No user is currently logged in.
    echo Please log in and run this script again.
    exit /b 1
)
:found_user

echo Detected user: %CONSOLE_USER%
echo.

set BAT_DIR=%~dp0
set TASK_NAME=RoyalKomputer Admin Push

REM Check if the task already exists
schtasks /Query /FO CSV /TN "%TASK_NAME%" >nul 2>&1
if %errorlevel% equ 0 (
    echo Task "%TASK_NAME%" already exists. Deleting and recreating...
    schtasks /Delete /TN "%TASK_NAME%" /F >nul 2>&1
)

echo Creating task "%TASK_NAME%" as user %CONSOLE_USER%...
echo.

REM Create the task using XML for reliability
set XML_FILE=%TEMP%\royal_push_task.xml

REM Get the domain-qualified username
for /f "tokens=*" %%d in ('whoami') do set FULL_USER=%%d
for /f "tokens=1 delims=\" %%d in ("%FULL_USER%") do set DOMAIN=%%d

REM Write the XML
> "%XML_FILE%" echo ^<?xml version="1.0" encoding="UTF-16"?^>
>> "%XML_FILE%" echo ^<Task version="1.3" xmlns="http://schemas.microsoft.com/windows/2004/02/mit/task"^>
>> "%XML_FILE%" echo   ^<RegistrationInfo^>
>> "%XML_FILE%" echo     ^<Description^>Admin push to GitHub when triggered by admin panel^</Description^>
>> "%XML_FILE%" echo     ^<URI^>\%TASK_NAME%^</URI^>
>> "%XML_FILE%" echo   ^</RegistrationInfo^>
>> "%XML_FILE%" echo   ^<Principals^>
>> "%XML_FILE%" echo     ^<Principal id="Author"^>
>> "%XML_FILE%" echo       ^<UserId^>%DOMAIN%\%CONSOLE_USER%^</UserId^>
>> "%XML_FILE%" echo       ^<LogonType^>S4U^</LogonType^>
>> "%XML_FILE%" echo       ^<RunLevel^>HighestAvailable^</RunLevel^>
>> "%XML_FILE%" echo     ^</Principal^>
>> "%XML_FILE%" echo   ^</Principals^>
>> "%XML_FILE%" echo   ^<Settings^>
>> "%XML_FILE%" echo     ^<DisallowStartIfOnBatteries^>false^</DisallowStartIfOnBatteries^>
>> "%XML_FILE%" echo     ^<StartWhenAvailable^>true^</StartWhenAvailable^>
>> "%XML_FILE%" echo     ^<MultipleInstancesPolicy^>IgnoreNew^</MultipleInstancesPolicy^>
>> "%XML_FILE%" echo   ^</Settings^>
>> "%XML_FILE%" echo   ^<Actions Context="Author"^>
>> "%XML_FILE%" echo     ^<Exec^>
>> "%XML_FILE%" echo       ^<Command^>cmd.exe^</Command^>
>> "%XML_FILE%" echo       ^<Arguments^>/c "%BAT_DIR%push_admin.bat"^</Arguments^>
>> "%XML_FILE%" echo       ^<WorkingDirectory^>%BAT_DIR%^</WorkingDirectory^>
>> "%XML_FILE%" echo     ^</Exec^>
>> "%XML_FILE%" echo   ^</Actions^>
>> "%XML_FILE%" echo ^</Task^>

REM Import the task
schtasks /Create /XML "%XML_FILE%" /TN "%TASK_NAME%" /F >nul 2>&1
if %errorlevel% equ 0 (
    echo SUCCESS: Task "%TASK_NAME%" created.
    echo.
    echo You can now click "Push" in the admin panel and it will
    echo run push_admin.bat as %CONSOLE_USER%.
) else (
    echo ERROR: Failed to create task. Try running as Administrator.
    exit /b 1
)

REM Clean up
del "%XML_FILE%" >nul 2>&1

echo.
echo Done.
exit /b 0