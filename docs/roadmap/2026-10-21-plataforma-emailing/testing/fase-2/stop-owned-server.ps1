$ErrorActionPreference = 'Stop'
$qaOut = Split-Path -Parent $MyInvocation.MyCommand.Path
$qaOriginal = Get-Content -LiteralPath "$qaOut/raw/artifacts/server-off-process.json" -Raw | ConvertFrom-Json
$qaPid = [int]$qaOriginal.ProcessId
$qaCurrent = Get-CimInstance Win32_Process -Filter "ProcessId = $qaPid"
if (-not $qaCurrent) { Write-Output 'Owned server already absent'; exit 0 }
$qaOriginalMillis = ([DateTimeOffset]$qaOriginal.CreationDate.ToUniversalTime()).ToUnixTimeMilliseconds()
$qaCurrentMillis = ([DateTimeOffset]$qaCurrent.CreationDate.ToUniversalTime()).ToUnixTimeMilliseconds()
if ($qaCurrentMillis -ne $qaOriginalMillis -or $qaCurrent.ParentProcessId -ne $qaOriginal.ParentProcessId -or $qaCurrent.ExecutablePath -ne $qaOriginal.ExecutablePath -or $qaCurrent.CommandLine -ne $qaOriginal.CommandLine) { throw 'Owned server identity mismatch' }
function Get-QaChildren([int]$qaParent) {
    foreach ($qaChild in @(Get-CimInstance Win32_Process -Filter "ParentProcessId = $qaParent")) {
        Get-QaChildren $qaChild.ProcessId
        $qaChild
    }
}
$qaTree = @(Get-QaChildren $qaPid)
foreach ($qaChild in $qaTree) {
    $qaAllowed = ($qaChild.Name -eq 'php.exe' -and $qaChild.ExecutablePath -eq $qaOriginal.ExecutablePath -and $qaChild.CommandLine.Contains('127.0.0.1:8084')) -or ($qaChild.Name -eq 'cmd.exe' -and $qaChild.ExecutablePath -eq "$env:WINDIR\System32\cmd.exe" -and $qaChild.CommandLine.Contains($qaOriginal.ExecutablePath) -and $qaChild.CommandLine.Contains('127.0.0.1:8084')) -or ($qaChild.Name -eq 'conhost.exe' -and $qaChild.ExecutablePath -eq "$env:WINDIR\System32\conhost.exe" -and $qaChild.ParentProcessId -eq $qaPid)
    if (-not $qaAllowed) { throw 'Unexpected process in owned server tree' }
}
@($qaTree + $qaCurrent) | Select-Object ProcessId,ParentProcessId,ExecutablePath,CommandLine,@{Name='CreatedUtc';Expression={$_.CreationDate.ToUniversalTime().ToString('o')}} | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath "$qaOut/raw/artifacts/server-off-verified-tree.json" -Encoding UTF8
foreach ($qaChild in $qaTree) { Stop-Process -Id $qaChild.ProcessId -Force }
Stop-Process -Id $qaPid -Force
Write-Output 'Owned server root and verified PHP/cmd/conhost children stopped'
