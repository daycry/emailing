param([Parameter(Mandatory = $true)][string]$Path)
$ErrorActionPreference = 'Stop'
$resolved = (Resolve-Path -LiteralPath $Path).Path
$projectRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '../..')).Path
$storageRoot = $projectRoot + [IO.Path]::DirectorySeparatorChar + 'storage' + [IO.Path]::DirectorySeparatorChar
if (!$resolved.StartsWith($storageRoot, [StringComparison]::OrdinalIgnoreCase)) { throw 'Private QA file must remain inside owned project storage.' }
if ((Get-Item -LiteralPath $resolved).PSIsContainer) { throw 'A regular private QA file is required.' }
$sid = [Security.Principal.WindowsIdentity]::GetCurrent().User
$acl = New-Object Security.AccessControl.FileSecurity
$acl.SetOwner($sid)
$acl.SetAccessRuleProtection($true, $false)
$acl.AddAccessRule((New-Object Security.AccessControl.FileSystemAccessRule($sid, 'FullControl', 'Allow')))
$acl.AddAccessRule((New-Object Security.AccessControl.FileSystemAccessRule((New-Object Security.Principal.SecurityIdentifier('S-1-5-18')), 'FullControl', 'Allow')))
Set-Acl -LiteralPath $resolved -AclObject $acl
