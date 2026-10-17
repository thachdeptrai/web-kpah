
@echo off
setlocal EnableExtensions
title Git Auto Pull - Full Logging

REM ===== CAU HINH =====
set "REPO=C:\xampp\htdocs"
set "BRANCH=main"
set "INTERVAL=10"
set "LOG=%~dp0auto-pull.log"

cd /d "%REPO%" || (
    echo ERROR: Khong tim thay thu muc %REPO%
    pause
    exit /b 1
)

REM ===== BAT DAU =====
call :log "=================================================="
call :log "AUTO PULL STARTED"
call :log "Repository: %REPO%"
call :log "Branch: %BRANCH%"
call :log "Interval: %INTERVAL% seconds"
call :log "=================================================="

git rev-parse --is-inside-work-tree >> "%LOG%" 2>&1
if errorlevel 1 (
    call :log "ERROR: Thu muc khong phai Git repository."
    pause
    exit /b 1
)

:loop
call :log "CHECK: Bat dau kiem tra remote..."

for /f "delims=" %%i in ('git rev-parse HEAD 2^>nul') do set "OLD_COMMIT=%%i"
call :log "LOCAL HEAD: %OLD_COMMIT%"

git fetch origin %BRANCH% >> "%LOG%" 2>&1
if errorlevel 1 (
    call :log "FETCH FAILED: Kiem tra mang, remote hoac dang nhap GitHub."
    goto wait
)
call :log "FETCH OK"

for /f "delims=" %%i in ('git rev-parse refs/remotes/origin/%BRANCH% 2^>nul') do set "REMOTE_COMMIT=%%i"
call :log "REMOTE HEAD: %REMOTE_COMMIT%"

if not defined REMOTE_COMMIT (
    call :log "ERROR: Khong doc duoc commit remote."
    goto wait
)

if "%OLD_COMMIT%"=="%REMOTE_COMMIT%" (
    call :log "NO CHANGE: Khong co commit moi."
    goto wait
)

call :log "UPDATE DETECTED: Remote khac local."
call :log "PULL STARTED: git pull --ff-only origin %BRANCH%"

git pull --ff-only origin %BRANCH% >> "%LOG%" 2>&1
if errorlevel 1 (
    call :log "PULL FAILED: Khong tu dong reset hoac xoa code local."
    call :log "Can kiem tra log Git phia tren de xu ly loi."
    goto wait
)

for /f "delims=" %%i in ('git rev-parse HEAD 2^>nul') do set "NEW_COMMIT=%%i"
call :log "PULL SUCCESS"
call :log "NEW LOCAL HEAD: %NEW_COMMIT%"

:wait
timeout /t %INTERVAL% /nobreak >nul
goto loop

:log
echo [%date% %time%] %~1
>> "%LOG%" echo [%date% %time%] %~1
exit /b
