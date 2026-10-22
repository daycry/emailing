$projectRoot = Split-Path $PSScriptRoot -Parent
$iniDir = Join-Path $projectRoot 'storage/php-conf'
[IO.Directory]::CreateDirectory($iniDir) | Out-Null
[IO.File]::WriteAllText((Join-Path $iniDir 'sockets.ini'), "extension=sockets`n", (New-Object Text.UTF8Encoding($false)))
$env:PHP_INI_SCAN_DIR = $iniDir
Copy-Item -LiteralPath (Join-Path $projectRoot 'docker/emailing-uploads.ini') -Destination (Join-Path $iniDir 'uploads.ini') -Force
& composer @args
exit $LASTEXITCODE
