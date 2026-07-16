@echo off
title Dentfluence HQ - one-time setup
cd /d "%~dp0"
echo ============================================
echo  Dentfluence HQ - Command Center setup
echo  Target: %~dp0  (confirmed: the copy Laragon serves)
echo ============================================
echo.
echo [1/3] Migrations (clinics, plans, subscriptions, tickets, superadmin flag)...
php artisan migrate
if errorlevel 1 goto :fail
echo.
echo [2/3] Seeding plans from the 2026 pricing doc (with pass unlocks)...
php artisan db:seed --class=PlanSeeder
if errorlevel 1 goto :fail
echo.
echo [3/3] Granting superadmin to sumitfirke1@gmail.com...
php artisan tinker --execute="$n = \App\Models\User::where('email','sumitfirke1@gmail.com')->update(['is_superadmin'=>true]); echo $n ? 'OK - superadmin set' : 'WARNING: no user with that email - set the flag on your actual OS login email instead';"
echo.
echo ============================================
echo  Done. Open:  http://dentfluence.test/hq
echo  (log in with your normal OS login first)
echo ============================================
pause
goto :eof
:fail
echo.
echo Something failed above. Copy the error and paste it to Claude.
pause
