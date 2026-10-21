$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$iniDir = Join-Path $projectRoot 'storage/php-conf'
[IO.Directory]::CreateDirectory($iniDir) | Out-Null
[IO.File]::WriteAllText((Join-Path $iniDir 'sockets.ini'),"extension=sockets`n",(New-Object Text.UTF8Encoding($false)))
function Read-Settings($path) {
    $values = @{}
    foreach ($line in [IO.File]::ReadAllLines($path)) {
        if ($line -match '^([A-Z_]+)=(.*)$') { $values[$matches[1]] = $matches[2] }
    }
    return $values
}
function New-Secret {
    $bytes = New-Object byte[] 32
    $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($bytes) } finally { $rng.Dispose() }
    return ([BitConverter]::ToString($bytes)).Replace('-', '').ToLowerInvariant()
}
$infra = Read-Settings (Join-Path $projectRoot 'infra/.env')
$appPath = Join-Path $projectRoot '.env'
if (!(Test-Path -LiteralPath $appPath)) {
    $app = [IO.File]::ReadAllText((Join-Path $projectRoot '.env.example'))
    $keyBytes = New-Object byte[] 32
    $keyRng = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $keyRng.GetBytes($keyBytes) } finally { $keyRng.Dispose() }
    $key = [Convert]::ToBase64String($keyBytes)
    $app = $app.Replace('APP_KEY=', "APP_KEY=base64:$key").Replace('DB_PASSWORD=', "DB_PASSWORD=$($infra.MYSQL_EMAILING_PASSWORD)").Replace('RABBITMQ_PASSWORD=', "RABBITMQ_PASSWORD=$(New-Secret)")
    [IO.File]::WriteAllText($appPath,$app,(New-Object Text.UTF8Encoding($false)))
}
$app = Read-Settings $appPath
if ($app.RABBITMQ_USER -ne 'emailing-app' -or $app.RABBITMQ_VHOST -ne 'emailing') { throw 'Refusing to provision unexpected RabbitMQ identity/vhost.' }
$pair = "$($infra.RABBITMQ_ADMIN_USER):$($infra.RABBITMQ_ADMIN_PASSWORD)"
$headers = @{Authorization='Basic '+[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($pair))}
function Rabbit-Put($path,$data) {
    Invoke-RestMethod -Method Put -Uri "http://127.0.0.1:15672/api/$path" -Headers $headers -ContentType 'application/json' -Body ($data|ConvertTo-Json -Compress) | Out-Null
}
Rabbit-Put 'vhosts/emailing' @{}
Rabbit-Put 'users/emailing-app' @{password=$app.RABBITMQ_PASSWORD;tags=''}
Rabbit-Put 'permissions/emailing/emailing-app' @{configure='.*';write='.*';read='.*'}
$testingPath = Join-Path $projectRoot '.env.testing'
if (!(Test-Path -LiteralPath $testingPath)) {
    $testing = [IO.File]::ReadAllText($appPath).Replace('APP_ENV=local','APP_ENV=testing').Replace('DB_DATABASE=emailing','DB_DATABASE=emailing_testing').Replace('DB_USERNAME=emailing','DB_USERNAME=emailing_testing').Replace("DB_PASSWORD=$($app.DB_PASSWORD)","DB_PASSWORD=$(New-Secret)")
    $testing += "`nBCRYPT_ROUNDS=4`nCACHE_STORE=array`nSESSION_DRIVER=array`nQUEUE_CONNECTION=sync`nMAIL_MAILER=array`n"
    [IO.File]::WriteAllText($testingPath,$testing,(New-Object Text.UTF8Encoding($false)))
}
$testing = Read-Settings $testingPath
if ($testing.DB_DATABASE -ne 'emailing_testing' -or $testing.DB_USERNAME -ne 'emailing_testing' -or $testing.DB_PASSWORD -notmatch '^[a-f0-9]{64}$') { throw 'Unexpected testing database credentials; refusing changes.' }
$query = "CREATE DATABASE IF NOT EXISTS emailing_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS 'emailing_testing'@'%' IDENTIFIED BY '$($testing.DB_PASSWORD)'; ALTER USER 'emailing_testing'@'%' IDENTIFIED BY '$($testing.DB_PASSWORD)'; GRANT ALL PRIVILEGES ON emailing_testing.* TO 'emailing_testing'@'%';"
& docker compose -f (Join-Path $projectRoot 'infra/compose.yaml') exec -T -e "MYSQL_PWD=$($infra.MYSQL_ROOT_PASSWORD)" mysql mysql --user=root --execute=$query
if ($LASTEXITCODE -ne 0) { throw 'Testing database provisioning failed.' }
Write-Host 'Application vhost/user and isolated testing database provisioned. Existing app secrets and data preserved.'
