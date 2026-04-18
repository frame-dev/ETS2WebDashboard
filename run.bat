@echo off
setlocal EnableExtensions

cd /d "%~dp0"

if not defined HOST set "HOST=0.0.0.0"
if not defined PORT set "PORT=8000"
if not defined PHP_VERSION set "PHP_VERSION=8.3"
for /f "tokens=1,2 delims=." %%A in ("%PHP_VERSION%") do set "PHP_SERIES=%%A.%%B"
set "PHP_ROOT=%~dp0.runtime\php"
set "PHP_EXE=%PHP_ROOT%\php.exe"
set "PHP_ZIP=%TEMP%\php-%PHP_VERSION%-Win32-x64.zip"

if not exist "%PHP_EXE%" (
    echo Downloading portable PHP %PHP_SERIES% runtime...

    if not exist "%~dp0.runtime" mkdir "%~dp0.runtime"
    if not exist "%PHP_ROOT%" mkdir "%PHP_ROOT%"

    powershell -NoProfile -ExecutionPolicy Bypass -Command ^
        "$ProgressPreference = 'SilentlyContinue';" ^
        "$version = '%PHP_VERSION%';" ^
        "$series = '%PHP_SERIES%';" ^
        "$zipPath = '%PHP_ZIP%';" ^
        "$destination = '%PHP_ROOT%';" ^
        "$curl = Get-Command curl.exe -ErrorAction SilentlyContinue;" ^
        "$candidates = New-Object 'System.Collections.Generic.List[string]';" ^
        "$candidates.Add('https://downloads.php.net/~windows/releases/latest/php-' + $series + '-Win32-vs16-x64-latest.zip');" ^
        "$candidates.Add('https://downloads.php.net/~windows/releases/latest/php-' + $series + '-nts-Win32-vs16-x64-latest.zip');" ^
        "$candidates.Add('https://windows.php.net/downloads/releases/latest/php-' + $series + '-Win32-vs16-x64-latest.zip');" ^
        "$candidates.Add('https://windows.php.net/downloads/releases/latest/php-' + $series + '-nts-Win32-vs16-x64-latest.zip');" ^
        "if ($version -match '^\d+\.\d+\.\d+$') {" ^
        "  $candidates.Add('https://windows.php.net/downloads/releases/php-' + $version + '-Win32-vs16-x64.zip');" ^
        "  $candidates.Add('https://windows.php.net/downloads/releases/archives/php-' + $version + '-Win32-vs16-x64.zip');" ^
        "  $candidates.Add('https://windows.php.net/downloads/releases/php-' + $version + '-nts-Win32-vs16-x64.zip');" ^
        "  $candidates.Add('https://windows.php.net/downloads/releases/archives/php-' + $version + '-nts-Win32-vs16-x64.zip');" ^
        "}" ^
        "foreach ($url in $candidates) {" ^
        "  try {" ^
        "    if (Test-Path -LiteralPath $zipPath) { Remove-Item -LiteralPath $zipPath -Force -ErrorAction SilentlyContinue };" ^
        "    if (Test-Path -LiteralPath $destination) { Get-ChildItem -LiteralPath $destination -Force -ErrorAction SilentlyContinue | Remove-Item -Recurse -Force -ErrorAction SilentlyContinue };" ^
        "    if ($curl) {" ^
        "      & $curl.Source '--fail' '--location' '--silent' '--show-error' '--connect-timeout' '10' '--max-time' '180' '--output' $zipPath $url;" ^
        "      if ($LASTEXITCODE -ne 0) { throw ('curl.exe exited with code ' + $LASTEXITCODE) };" ^
        "    } else {" ^
        "      Invoke-WebRequest -UseBasicParsing -MaximumRedirection 5 -TimeoutSec 20 -Uri $url -OutFile $zipPath -ErrorAction Stop;" ^
        "    }" ^
        "    Expand-Archive -LiteralPath $zipPath -DestinationPath $destination -Force -ErrorAction Stop;" ^
        "    if (Test-Path -LiteralPath (Join-Path $destination 'php.exe')) { Write-Host ('Downloaded PHP from ' + $url); exit 0 };" ^
        "  } catch {" ^
        "    Write-Host ('Download attempt failed: ' + $url + ' (' + $_.Exception.Message + ')');" ^
        "  }" ^
        "}" ^
        "Write-Error ('Failed to download a working PHP build for version ' + $version + ' (series ' + $series + ').');" ^
        "exit 1"

    if errorlevel 1 (
        echo Failed to download or extract PHP from the official Windows PHP mirrors.
        exit /b 1
    )
)

if not exist "%PHP_ROOT%\php.ini" (
    if exist "%PHP_ROOT%\php.ini-development" (
        copy /y "%PHP_ROOT%\php.ini-development" "%PHP_ROOT%\php.ini" >nul
    )
)

REM Enable extensions required for HTTPS requests (openssl, curl) and set extension_dir
REM Also download Mozilla CA bundle and configure curl.cainfo / openssl.cafile
if exist "%PHP_ROOT%\php.ini" (
    if not exist "%PHP_ROOT%\extras\ssl" mkdir "%PHP_ROOT%\extras\ssl"
    if not exist "%PHP_ROOT%\extras\ssl\cacert.pem" (
        echo Downloading Mozilla CA certificate bundle...
        powershell -NoProfile -ExecutionPolicy Bypass -Command ^
            "$ProgressPreference = 'SilentlyContinue';" ^
            "$curl = Get-Command curl.exe -ErrorAction SilentlyContinue;" ^
            "if ($curl) { & $curl.Source '--fail' '--silent' '--show-error' '--location' '--output' '%PHP_ROOT%\extras\ssl\cacert.pem' 'https://curl.se/ca/cacert.pem' }" ^
            "else { Invoke-WebRequest -UseBasicParsing -Uri 'https://curl.se/ca/cacert.pem' -OutFile '%PHP_ROOT%\extras\ssl\cacert.pem' -ErrorAction Stop }"
    )
    powershell -NoProfile -ExecutionPolicy Bypass -Command ^
        "$ini = '%PHP_ROOT%\php.ini';" ^
        "$ca = '%PHP_ROOT%\extras\ssl\cacert.pem';" ^
        "$c = Get-Content $ini -Raw;" ^
        "if ($c -match '(?m)^;extension_dir\s*=\s*\"ext\"') { $c = $c -replace '(?m)^;extension_dir\s*=\s*\"ext\"', 'extension_dir = \"ext\"' };" ^
        "if ($c -match '(?m)^;extension=openssl') { $c = $c -replace '(?m)^;extension=openssl', 'extension=openssl' };" ^
        "if ($c -match '(?m)^;extension=curl') { $c = $c -replace '(?m)^;extension=curl', 'extension=curl' };" ^
        "if (Test-Path $ca) {" ^
        "  $c = $c -replace '(?m)^;curl\.cainfo\s*=.*$', ('curl.cainfo = \"' + $ca + '\"');" ^
        "  $c = $c -replace '(?m)^;openssl\.cafile\s*=.*$', ('openssl.cafile = \"' + $ca + '\"');" ^
        "}" ^
        "Set-Content $ini $c -NoNewline"
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
