@echo off
setlocal
cd /d "%~dp0.."
set "PHP_EXE=C:\xampp\php\php.exe"
set "MONITOR=%CD%\tools\farmer-monitor.php"
if not exist "%PHP_EXE%" (
  echo ERROR: PHP missing. Edit PHP_EXE in this file.
  exit /b 1
)
"%PHP_EXE%" "%MONITOR%" install
if errorlevel 1 exit /b 1
"%PHP_EXE%" "%MONITOR%" collect
if errorlevel 1 exit /b 1
schtasks /Create /F /SC MINUTE /MO 5 /TN "KPAH Farmer Monitor" /TR "\"%PHP_EXE%\" \"%MONITOR%\" collect" /RL LIMITED
if errorlevel 1 (
  echo ERROR: Cannot create scheduled task. Run as admin.
  exit /b 1
)
echo Scheduled every 5 minutes.
endlocal
