$ErrorActionPreference = 'Stop'
$envPath = Join-Path $PSScriptRoot '.env'
if (Test-Path -LiteralPath $envPath) {
    Write-Host 'infra/.env already exists; credentials preserved.'
    exit 0
}
function New-LabSecret {
    $randomBytes = New-Object byte[] 32
    $generator = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $generator.GetBytes($randomBytes) } finally { $generator.Dispose() }
    return ([BitConverter]::ToString($randomBytes)).Replace('-', '').ToLowerInvariant()
}
$settings = @(
    '# Local laboratory secrets. Do not commit or share this file.'
    'RABBITMQ_ADMIN_USER=labadmin'
    "RABBITMQ_ADMIN_PASSWORD=$(New-LabSecret)"
    "MYSQL_ROOT_PASSWORD=$(New-LabSecret)"
    "MYSQL_EMAILING_PASSWORD=$(New-LabSecret)"
    "CRONICLE_SECRET_KEY=$(New-LabSecret)"
    "CRONICLE_ADMIN_PASSWORD=$(New-LabSecret)"
)
[IO.File]::WriteAllText($envPath, ($settings -join "`n") + "`n", (New-Object Text.UTF8Encoding($false)))
Write-Host 'Created infra/.env with random local credentials. Open the file locally to retrieve them.'
