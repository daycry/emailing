$projectRoot = Split-Path $PSScriptRoot -Parent
$iniDir = Join-Path $projectRoot 'storage/php-conf'
foreach ($directory in @('storage/php-conf', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'storage/app/private', 'bootstrap/cache')) {
    [IO.Directory]::CreateDirectory((Join-Path $projectRoot $directory)) | Out-Null
}
[IO.File]::WriteAllText((Join-Path $iniDir 'sockets.ini'), "extension=sockets`n", (New-Object Text.UTF8Encoding($false)))
$env:PHP_INI_SCAN_DIR = $iniDir
Copy-Item -LiteralPath (Join-Path $projectRoot 'docker/emailing-uploads.ini') -Destination (Join-Path $iniDir 'uploads.ini') -Force
& php @args
exit $LASTEXITCODE
