@echo off
title Dentfluence HQ - grant access to your real login
cd /d "%~dp0"
echo Current users in the system:
echo.
php artisan tinker --execute="foreach(\App\Models\User::all(['id','name','email','role','is_superadmin']) as $u){echo $u->id.' | '.$u->email.' | role: '.($u->role ?? '-').' | superadmin: '.($u->is_superadmin ? 'YES' : 'no').PHP_EOL;}"
echo.
echo Granting superadmin to every admin-role user...
php artisan tinker --execute="$n = \App\Models\User::where('role','admin')->update(['is_superadmin'=>true]); echo $n.' admin user(s) granted superadmin'.PHP_EOL;"
echo.
echo Done. Refresh http://dentfluence.test/hq
echo If it still says 403, tell Claude which email from the list above you log in with.
pause
