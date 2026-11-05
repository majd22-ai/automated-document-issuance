@echo off
title MySQL Server - E-Issuance
echo ========================================
echo  Starting MySQL Server for E-Issuance
echo ========================================
echo.
echo MySQL is starting... Please wait.
echo Do NOT close this window while working!
echo.
"C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone --console
pause
