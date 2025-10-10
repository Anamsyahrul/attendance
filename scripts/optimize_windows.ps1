<#
.SYNOPSIS
  Optimize Windows for performance on developer laptops.

.DESCRIPTION
  - Optionally enable and set "Ultimate Performance" power plan
  - Optimize SSD (TRIM/defrag optimize)
  - Optionally enable Hardware-Accelerated GPU Scheduling (HAGS)

.USAGE
  Run in elevated PowerShell:
    ./optimize_windows.ps1 -EnableUltimate -OptimizeStorage -EnableHAGS

  Flags are optional; omit any you don't want to apply.
#>
[CmdletBinding(SupportsShouldProcess=$true)]
param(
  [switch]$EnableUltimate,
  [switch]$OptimizeStorage,
  [switch]$EnableHAGS
)

function Assert-Admin {
  $id = [Security.Principal.WindowsIdentity]::GetCurrent()
  $p = New-Object Security.Principal.WindowsPrincipal($id)
  if(-not $p.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)){
    throw 'Please run this script in an elevated PowerShell (Run as Administrator).'
  }
}

function Enable-UltimatePerformancePlan {
  Write-Host 'Enabling Ultimate Performance power plan if missing...'
  $guid = 'e9a42b02-d5df-448d-aa00-03f14749eb61'
  $existing = (powercfg /l) 2>$null
  if($existing -and ($existing -match $guid)){
    Write-Host 'Plan already present. Activating it...'
  } else {
    powercfg -duplicatescheme $guid | Out-Null
  }
  powercfg -setactive $guid
  Write-Host 'Ultimate Performance plan is now active.'
}

function Optimize-Storage {
  Write-Host 'Running storage optimization (TRIM/defrag optimize) on all volumes...'
  defrag /C /O /H /U /V | Out-Host
  Write-Host 'Storage optimization complete.'
}

function Enable-HAGS {
  Write-Host 'Enabling Hardware-Accelerated GPU Scheduling (HAGS)...'
  $key = 'HKLM:\SYSTEM\CurrentControlSet\Control\GraphicsDrivers'
  New-Item -Path $key -Force | Out-Null
  New-ItemProperty -Path $key -Name 'HwSchMode' -PropertyType DWord -Value 2 -Force | Out-Null
  $cur = (Get-ItemProperty -Path $key -Name 'HwSchMode' -ErrorAction SilentlyContinue).HwSchMode
  Write-Host "HAGS registry value set to: $cur (2 = Enabled). A reboot may be required."
}

try {
  Assert-Admin
  if($EnableUltimate){ Enable-UltimatePerformancePlan }
  if($OptimizeStorage){ Optimize-Storage }
  if($EnableHAGS){ Enable-HAGS }
  if(-not ($EnableUltimate -or $OptimizeStorage -or $EnableHAGS)){
    Write-Host 'No flags specified. Nothing applied. Use -EnableUltimate -OptimizeStorage -EnableHAGS as needed.'
  }
  Write-Host 'Done.'
} catch {
  Write-Error $_
  exit 1
}

