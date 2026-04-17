@echo off
setlocal EnableExtensions

cd /d "%~dp0"

set "HOST=localhost"
set "PORT=8000"
set "PHP_VERSION=8.3.29"
set "PHP_ARCHIVE=php-%PHP_VERSION%-Win32-vs16-x64.zip"
set "PHP_URL=https://windows.php.net/downloads/releases/%PHP_ARCHIVE%"
set "PHP_ROOT=%~dp0.runtime\php"
set "PHP_EXE=%PHP_ROOT%\php.exe"
set "PHP_ZIP=%TEMP%\%PHP_ARCHIVE%"

if not exist "%PHP_EXE%" (
    echo Downloading portable PHP %PHP_VERSION%...

    if not exist "%~dp0.runtime" mkdir "%~dp0.runtime"
    if not exist "%PHP_ROOT%" mkdir "%PHP_ROOT%"

    powershell -NoProfile -ExecutionPolicy Bypass -Command ^
        "$ProgressPreference = 'SilentlyContinue';" ^
        "Invoke-WebRequest -Uri '%PHP_URL%' -OutFile '%PHP_ZIP%';" ^
        "Expand-Archive -LiteralPath '%PHP_ZIP%' -DestinationPath '%PHP_ROOT%' -Force"

    if errorlevel 1 (
        echo Failed to download or extract PHP from:
        echo %PHP_URL%
        exit /b 1
    )
)

if not exist "%PHP_ROOT%\php.ini" (
    if exist "%PHP_ROOT%\php.ini-development" (
        copy /y "%PHP_ROOT%\php.ini-development" "%PHP_ROOT%\php.ini" >nul
    )
)

"%PHP_EXE%" -v >nul 2>&1
if errorlevel 1 (
    echo Portable PHP was downloaded, but it could not start.
    echo You may need the Microsoft Visual C++ Redistributable ^(x64^):
    echo https://aka.ms/vs/17/release/vc_redist.x64.exe
    exit /b 1
)

echo Starting ETS2 Web Dashboard on http://%HOST%:%PORT%/
"%PHP_EXE%" -S %HOST%:%PORT% router.php
