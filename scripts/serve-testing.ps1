param([int]$Port = 8082)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$source = Join-Path $projectRoot '.env.testing'
if (!(Test-Path -LiteralPath $source)) { throw 'Run scripts/provision-local.ps1 first.' }
$content = [IO.File]::ReadAllText($source)
if ($content -notmatch '(?m)^DB_DATABASE=emailing_testing\r?$' -or $content -notmatch '(?m)^DB_USERNAME=emailing_testing\r?$') { throw 'Exclusive testing database required.' }
if ($content -notmatch '(?m)^DB_CONNECTION=mysql\r?$' -or $content -match '(?m)^DB_URL=\S+') { throw 'MySQL without DB_URL override required.' }
$content = $content -replace '(?m)^APP_ENV=.*$', 'APP_ENV=local'
$content = $content -replace '(?m)^APP_URL=.*$', "APP_URL=http://127.0.0.1:$Port"
# Local app environment keeps real HTTP CSRF validation active; the database stays isolated.
$content += "`nSESSION_DRIVER=file`nSESSION_COOKIE=emailing-qa-session`nCACHE_STORE=file`nMAIL_MAILER=smtp`n"
[IO.File]::WriteAllText((Join-Path $projectRoot '.env.qa'), $content, (New-Object Text.UTF8Encoding($false)))
$env:PHP_INI_SCAN_DIR = Join-Path $projectRoot 'storage/php-conf'
& php (Join-Path $projectRoot 'scripts/prepare-runtime.php')
if ($LASTEXITCODE -ne 0) { throw 'Runtime preparation failed.' }
$php = (Get-Command php).Source
$process = Start-Process -FilePath $php -ArgumentList @('artisan', 'serve', '--env=qa', '--host=127.0.0.1', "--port=$Port", '--no-reload') -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $projectRoot 'storage/logs/qa-server.out.log') -RedirectStandardError (Join-Path $projectRoot 'storage/logs/qa-server.err.log') -PassThru
Write-Host "QA server started: PID $($process.Id), http://127.0.0.1:$Port (emailing_testing only)."
