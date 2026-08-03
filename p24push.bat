@echo off
cd /d "E:\Dentfluence\Dentfluence_OS\Dentfluence Web"
del /q ".git\index.lock" 2>nul
del /q p24b2.bat p24d2.bat p24ec.bat 2>nul

echo ================= COMMIT THE CENSUS FILE =====================
REM Force-added: a blanket *.sql ignore rule is right for dumps, wrong for a
REM read-only documentation query set that the 2.4a report references by path.
git add -f docs/slice-2_4a-clinical-progress-census.sql
git commit -m "Docs: Slice 2.4a read-only production census queries" -m "Every statement is a SELECT. Referenced by name from the 2.4a report, so it belongs in the repo despite the blanket *.sql ignore rule that exists to keep database dumps out."

echo ================= WHAT IS BEING PUSHED =======================
git log --oneline d9bcdf9..HEAD
git status --short

echo ================= PUSH =======================================
git push origin main

echo ================= RESULT =====================================
git log --oneline -1
echo ================= DONE =======================================
echo.
echo NOTE: pushing does NOT deploy. Production still runs 79c0a9a
echo       (Slice 2.2). Slices 2.3 and 2.4 are NOT live.
