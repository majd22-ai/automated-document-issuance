@echo off
title E-Issuance Dev Server
echo ========================================
echo  Starting E-Issuance Development Server
echo ========================================
echo.
echo [1/2] Starting MySQL...
start "MySQL Server" "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone --console
echo Waiting for MySQL to initialize (8 seconds)...
timeout /t 8 /nobreak > nul

echo.
echo [2/2] Starting Laravel...
cd /d C:\Users\w3h333\e-issuance
php artisan serve --host=0.0.0.0 --port=8000

pause
