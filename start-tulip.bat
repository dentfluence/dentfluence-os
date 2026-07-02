@echo off
title Dentfluence (Tulip) - local server
cd /d C:\laragon\www\dentfluence
echo Starting Dentfluence on http://127.0.0.1:8000
echo Keep this window open while you use the app. Close it to stop.
echo.
php artisan serve --host=127.0.0.1 --port=8000
pause
