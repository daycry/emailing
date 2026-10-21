$env:PHP_INI_SCAN_DIR = Join-Path (Split-Path $PSScriptRoot -Parent) 'storage/php-conf'
& php @args
exit $LASTEXITCODE
