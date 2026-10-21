param([switch]$VerifyPersistence)
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$composeArgs = @('compose', '--env-file', (Join-Path $PSScriptRoot '.env'), '-f', (Join-Path $PSScriptRoot 'compose.yaml'))
$settings = @{}
foreach ($line in [IO.File]::ReadAllLines((Join-Path $PSScriptRoot '.env'))) {
    if ($line -match '^([A-Z_]+)=(.*)$') { $settings[$matches[1]] = $matches[2] }
}
$runtimeDir = Join-Path $PSScriptRoot '.runtime'
$statePath = Join-Path $runtimeDir 'smoke-state.json'
$prior = $null
if ($VerifyPersistence -and (Test-Path -LiteralPath $statePath)) { $prior = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json }
if ($VerifyPersistence -and !$prior) { throw 'No previous smoke markers exist. Run smoke.ps1 before recreating containers.' }
$marker = if ($prior) { $prior.marker } else { [Guid]::NewGuid().ToString('N') }
$queue = "lab.smoke.$marker"
$rabbitHeaders = @{ Authorization = 'Basic ' + [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($settings.RABBITMQ_ADMIN_USER + ':' + $settings.RABBITMQ_ADMIN_PASSWORD)) }
function Rabbit-Request($method, $path, $body) {
    $arguments = @{ Method = $method; Uri = "http://127.0.0.1:15672/api/$path"; Headers = $rabbitHeaders; TimeoutSec = 15 }
    if ($null -ne $body) { $arguments.ContentType = 'application/json'; $arguments.Body = $body | ConvertTo-Json -Depth 10 -Compress }
    return Invoke-RestMethod @arguments
}
function Cronicle-Request($path, $body) {
    if (!$body) { $body = @{} }
    if ($script:sessionId) { $body.session_id = $script:sessionId }
    $reply = Invoke-RestMethod -Method Post -Uri "http://127.0.0.1:3012/api/$path" -ContentType 'application/json' -Body ($body | ConvertTo-Json -Depth 10 -Compress) -TimeoutSec 15
    if ($reply.code -ne 0) { throw "Cronicle operation failed: $path (code $($reply.code))." }
    return $reply
}
function Sql-Query($sql) {
    $output = $sql | & docker @composeArgs exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql -u"$MYSQL_USER" "$MYSQL_DATABASE" --batch --skip-column-names'
    if ($LASTEXITCODE -ne 0) { throw 'MySQL query failed.' }
    return ($output -join "`n").Trim()
}

try {
    if (!$prior) {
        $null = Rabbit-Request Put "queues/emailing-lab/$queue" @{ durable = $true; auto_delete = $false; arguments = @{} }
        $published = Rabbit-Request Post 'exchanges/emailing-lab/amq.default/publish' @{
            properties = @{ delivery_mode = 2 }; routing_key = $queue; payload = $marker; payload_encoding = 'string'
        }
        if (!$published.routed) { throw 'RabbitMQ did not route the smoke message.' }
    }
    $messages = @(Rabbit-Request Post "queues/emailing-lab/$queue/get" @{ count = 1; ackmode = 'ack_requeue_true'; encoding = 'auto'; truncate = 1000 })
    if (!$messages.Count -or $messages[0].payload -ne $marker) { throw 'RabbitMQ persistent message verification failed.' }
    Write-Host 'PASS RabbitMQ: durable queue, publish and consume/requeue.'

    if (!$prior) {
        $null = Sql-Query "CREATE TABLE IF NOT EXISTS lab_smoke (marker CHAR(32) PRIMARY KEY, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP); INSERT IGNORE INTO lab_smoke (marker) VALUES ('$marker');"
    }
    if ((Sql-Query "SELECT marker FROM lab_smoke WHERE marker='$marker';") -ne $marker) { throw 'MySQL persistent row verification failed.' }
    Write-Host 'PASS MySQL: application user can write/read its database.'

    if (!$prior) {
        $message = New-Object Net.Mail.MailMessage('smoke@lab.invalid', 'inbox@lab.invalid', "Laboratory smoke $marker", "Infrastructure test $marker")
        $smtp = New-Object Net.Mail.SmtpClient('127.0.0.1', 11025)
        try { $smtp.Send($message) } finally { $message.Dispose(); $smtp.Dispose() }
    }
    $mail = Invoke-RestMethod -Uri ("http://127.0.0.1:18025/api/v1/search?query=" + [Uri]::EscapeDataString("subject:$marker")) -TimeoutSec 15
    if (!$mail.messages.Count) { throw 'Mailpit did not retain the SMTP test message.' }
    Write-Host 'PASS Mailpit: SMTP message available through its API.'

    $login = Cronicle-Request 'user/login' @{ username = 'admin'; password = $settings.CRONICLE_ADMIN_PASSWORD }
    $script:sessionId = $login.session_id
    if (!$script:sessionId) { throw 'Cronicle login did not return a session.' }
    if ($prior) {
        $previous = Cronicle-Request 'app/get_job_details' @{ id = $prior.jobId }
        if ($previous.job.code -ne 0) { throw 'Cronicle previous successful job is unavailable.' }
        $eventId = $prior.eventId
    } else {
        $categories = Cronicle-Request 'app/get_categories' @{}
        $plugins = Cronicle-Request 'app/get_plugins' @{}
        $plugin = $plugins.rows | Where-Object { $_.id -eq 'shellplug' -or $_.title -match 'Shell' } | Select-Object -First 1
        $category = $categories.rows | Where-Object { $_.enabled -ne 0 } | Select-Object -First 1
        $group = $login.server_groups | Select-Object -First 1
        if (!$plugin -or !$category -or !$group) { throw 'Cronicle default plugin, category or server group is missing.' }
        $created = Cronicle-Request 'app/create_event' @{
            title = 'Laboratory infrastructure smoke'; enabled = 0; category = $category.id; plugin = $plugin.id; target = $group.id
            params = @{ script = "#!/bin/sh`nprintf 'Laboratory smoke completed\n'`n"; json = 0; annotate = 0 }
            timing = @{}; timeout = 30; max_children = 1; catch_up = 0; multiplex = 0
        }
        $eventId = $created.id
    }
    $run = Cronicle-Request 'app/run_event' @{ id = $eventId }
    $jobId = @($run.ids)[0]
    if (!$jobId) { throw 'Cronicle did not start a smoke job.' }
    $finished = $false
    for ($attempt = 0; $attempt -lt 45; $attempt++) {
        Start-Sleep -Seconds 2
        try {
            $details = Cronicle-Request 'app/get_job_details' @{ id = $jobId }
            if ($details.job -and $null -ne $details.job.code) {
                if ($details.job.code -ne 0) { throw 'Cronicle smoke job exited unsuccessfully.' }
                $finished = $true
                break
            }
        } catch {
            if ($_.Exception.Message -eq 'Cronicle smoke job exited unsuccessfully.') { throw }
        }
    }
    if (!$finished) { throw 'Cronicle smoke job did not complete within 90 seconds.' }
    Write-Host 'PASS Cronicle: authenticated login and finite shell job completed.'
    if (!(Test-Path -LiteralPath $runtimeDir)) { $null = New-Item -ItemType Directory -Path $runtimeDir }
    @{ marker = $marker; eventId = $eventId; jobId = $jobId } | ConvertTo-Json | Set-Content -LiteralPath $statePath -Encoding UTF8
    if ($prior) { Write-Host 'PASS Persistence: previous RabbitMQ, MySQL, Mailpit and Cronicle markers retained.' }
    Write-Host 'PASS Infrastructure smoke completed. Test markers remain for recreation checks.'
} catch {
    # HTTP error bodies/headers may contain session data; print only our controlled failures.
    $safeMessage = $_.Exception.Message
    if ($safeMessage -match '^(RabbitMQ|MySQL|Mailpit|Cronicle|No previous)') { Write-Host "FAIL $safeMessage" }
    else { Write-Host 'FAIL Infrastructure request failed. Inspect service health and local logs; credentials were not printed.' }
    exit 1
} finally {
    if ($script:sessionId) { try { $null = Cronicle-Request 'user/logout' @{} } catch {} }
}
