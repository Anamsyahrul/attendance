<#
.SYNOPSIS
  Tune Laragon stack for better local dev performance (PHP + MySQL).

.DESCRIPTION
  - PHP: enable + tune OPcache; disable Xdebug by default (you can enable when needed)
  - MySQL: set sensible InnoDB and cache sizes based on system RAM (dev friendly)

.USAGE
  Run in elevated PowerShell (so files in C:\laragon can be edited):
    ./tune_laragon.ps1

  Restart Laragon after applying.
#>
[CmdletBinding(SupportsShouldProcess=$true)]
param()

function Assert-Admin {
  $id = [Security.Principal.WindowsIdentity]::GetCurrent()
  $p = New-Object Security.Principal.WindowsPrincipal($id)
  if(-not $p.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)){
    throw 'Please run this script in an elevated PowerShell (Run as Administrator).'
  }
}

function Get-RAMSizeGB {
  $cs = Get-CimInstance Win32_ComputerSystem
  return [math]::Floor($cs.TotalPhysicalMemory / 1GB)
}

function Backup-File([string]$path){
  if(Test-Path $path){
    $ts = Get-Date -Format 'yyyyMMdd-HHmmss'
    Copy-Item $path ("${path}.${ts}.bak") -Force
  }
}

function Set-Or-Append-Block([string]$file,[string]$startMarker,[string]$endMarker,[string]$block){
  $content = if(Test-Path $file){ Get-Content -Raw $file } else { '' }
  if([string]::IsNullOrEmpty($content)){
    Set-Content -Path $file -Value "$startMarker`r`n$block`r`n$endMarker`r`n" -Encoding UTF8
    return
  }
  $start = $content.IndexOf($startMarker)
  $end = $content.IndexOf($endMarker)
  if($start -ge 0 -and $end -gt $start){
    $head = $content.Substring(0,$start)
    $tail = $content.Substring($end + $endMarker.Length)
    $new = $head + $startMarker + "`r`n" + $block + "`r`n" + $endMarker + $tail
    Set-Content -Path $file -Value $new -Encoding UTF8
  } else {
    Add-Content -Path $file -Value "`r`n$startMarker`r`n$block`r`n$endMarker`r`n"
  }
}

function Tune-PHP {
  Write-Host 'Tuning PHP OPcache and disabling Xdebug by default...'
  $phpRoot = 'C:\laragon\bin\php'
  if(!(Test-Path $phpRoot)){ Write-Warning 'PHP root not found at C:\\laragon\\bin\\php'; return }
  Get-ChildItem $phpRoot -Directory | ForEach-Object {
    $ini = Join-Path $_.FullName 'php.ini'
    if(!(Test-Path $ini)){ return }
    Backup-File $ini
    # Disable Xdebug zend_extension lines
    $raw = Get-Content -Raw $ini
    $raw2 = ($raw -split "`r?`n") | ForEach-Object {
      if($_ -match '^\s*zend_extension\s*=\s*.*xdebug.*$'){ ';' + $_ } else { $_ }
    } | Out-String
    Set-Content -Path $ini -Value $raw2 -Encoding UTF8

    $start = '; Codex OPcache TUNE START'
    $end   = '; Codex OPcache TUNE END'
    $block = @'
[opcache]
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=100000
opcache.validate_timestamps=1
opcache.revalidate_freq=0
opcache.save_comments=1
opcache.fast_shutdown=1
'@
    Set-Or-Append-Block -file $ini -startMarker $start -endMarker $end -block $block
    Write-Host "Tuned: $ini"
  }
}

function Tune-MySQL {
  Write-Host 'Tuning MySQL (InnoDB + caches) for dev...'
  $ram = Get-RAMSizeGB
  # ~1/6 of RAM, clamped between 1024M and 4096M for dev
  $poolMB = [math]::Max(1024, [math]::Min(4096, [math]::Floor(($ram * 1024)/6)))
  $mysqlRoot = 'C:\laragon\bin\mysql'
  if(!(Test-Path $mysqlRoot)){ Write-Warning 'MySQL root not found at C:\\laragon\\bin\\mysql'; return }
  Get-ChildItem $mysqlRoot -Directory | ForEach-Object {
    $ini = Join-Path $_.FullName 'my.ini'
    if(!(Test-Path $ini)){ return }
    Backup-File $ini
    $start = '# Codex MYSQL TUNE START'
    $end   = '# Codex MYSQL TUNE END'
    $block = @"
[mysqld]
innodb_buffer_pool_size=${poolMB}M
innodb_log_file_size=512M
innodb_flush_log_at_trx_commit=2
innodb_flush_method=O_DIRECT
table_open_cache=2048
thread_cache_size=64
tmp_table_size=256M
max_heap_table_size=256M
max_connections=200
performance_schema=ON
skip_name_resolve=ON
sql_mode=""
"@
    Set-Or-Append-Block -file $ini -startMarker $start -endMarker $end -block $block
    Write-Host "Tuned: $ini (innodb_buffer_pool_size=${poolMB}M)"
  }
}

try {
  Assert-Admin
  Tune-PHP
  Tune-MySQL
  Write-Host 'Done. Please restart Laragon services to apply changes.'
} catch {
  Write-Error $_
  exit 1
}

