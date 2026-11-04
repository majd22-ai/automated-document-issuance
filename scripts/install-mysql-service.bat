@echo off
:: Install and start MySQL as Windows Service
:: Run this file as Administrator!

echo ============================================
echo  Installing MySQL as Windows Service
echo  Run this file as Administrator!
echo ============================================
echo.

:: Check if running as admin
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo ERROR: Please right-click and "Run as Administrator"
    pause
    exit /b 1
)

:: Stop any existing MySQL processes
taskkill /f /im mysqld.exe >nul 2>&1
timeout /t 2 /nobreak >nul

:: Remove old service if exists
sc stop mysql >nul 2>&1
sc delete mysql >nul 2>&1
timeout /t 2 /nobreak >nul

:: Install MySQL as a service
echo Installing MySQL service...
"C:\xampp\mysql\bin\mysqld.exe" --install mysql --defaults-file="C:\xampp\mysql\bin\my.ini"

:: Start the service
echo Starting MySQL service...
net start mysql

echo.
echo ============================================
if %errorLevel% == 0 (
    echo  MySQL service started successfully!
    echo  Now you can start Laravel normally.
) else (
    echo  ERROR: Could not start MySQL service.
    echo  Try starting MySQL from XAMPP Control Panel.
)
echo ============================================
pause
