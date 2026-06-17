@echo off
echo [Royal Komputer] Starting local development servers...
echo.
echo 1) Frontend (Netlify local) - http://localhost:8080
start "Frontend" cmd /c "php -S localhost:8080 -t frontend"

echo 2) Backend (Render local) - http://localhost:8081
start "Backend" cmd /c "php -S localhost:8081 -t backend"

echo.
echo Press any key to stop all servers...
pause >nul
taskkill /fi "WINDOWTITLE eq Frontend" /f >nul 2>&1
taskkill /fi "WINDOWTITLE eq Backend" /f >nul 2>&1
echo Servers stopped.
