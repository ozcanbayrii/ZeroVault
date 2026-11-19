@echo off
title DropVault Yerel Sunucu
echo ===================================================
echo   DropVault Yerel Sunucusu Baslatiliyor...
echo   Adres: http://localhost:8000
echo ===================================================

start "" http://localhost:8000

"D:\Xampp\php\php.exe" -S localhost:8000 -t "%~dp0" "%~dp0index.php"
pause
