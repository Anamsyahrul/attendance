<#
  Rollback KDE-like theming back to Windows defaults
  - Switch to Aero theme
  - Uninstall ExplorerPatcher and SecureUxTheme (best-effort)
  - Restore Explorer/taskbar defaults

  Run as Administrator:
    powershell -ExecutionPolicy Bypass -File "scripts\kde_dark_rollback.ps1"
#>
[CmdletBinding()]
param()

function Write-Step($msg){ Write-Host "[+] $msg" -ForegroundColor Cyan }
function Write-Warn($msg){ Write-Host "[!] $msg" -ForegroundColor Yellow }
function Write-Err($msg){ Write-Host "[x] $msg" -ForegroundColor Red }

try {
  $ErrorActionPreference = 'Stop'
  [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

  Write-Step 'Switching to default Windows theme'
  Start-Process 'C:\Windows\Resources\Themes\aero.theme'

  Write-Step 'Uninstalling ExplorerPatcher (best-effort)'
  $ep = Join-Path $env:TEMP 'ep_setup.exe'
  try {
    Invoke-WebRequest 'https://github.com/valinet/ExplorerPatcher/releases/latest/download/ep_setup.exe' -OutFile $ep -UseBasicParsing
    Start-Process $ep -ArgumentList '/uninstall /verysilent' -Wait
  } catch { Write-Warn "ExplorerPatcher uninstall failed: $($_.Exception.Message)" }

  Write-Step 'Uninstalling SecureUxTheme (best-effort)'
  $sutUrl = 'https://github.com/namazso/SecureUxTheme/releases/latest'
  Write-Warn "Open $sutUrl if uninstall is needed via GUI. Skipping automated uninstall."

  Write-Step 'Restoring Explorer/taskbar defaults'
  $adv='HKCU:\Software\Microsoft\Windows\CurrentVersion\Explorer\Advanced'
  New-Item -Path $adv -Force | Out-Null
  Set-ItemProperty -Path $adv -Name UseCompactMode -Type DWord -Value 0 -ErrorAction SilentlyContinue
  Set-ItemProperty -Path $adv -Name HideFileExt -Type DWord -Value 1 -ErrorAction SilentlyContinue
  Set-ItemProperty -Path $adv -Name Hidden -Type DWord -Value 2 -ErrorAction SilentlyContinue
  Set-ItemProperty -Path $adv -Name TaskbarSi -Type DWord -Value 1 -ErrorAction SilentlyContinue
  Set-ItemProperty -Path $adv -Name TaskbarAl -Type DWord -Value 1 -ErrorAction SilentlyContinue
  Set-ItemProperty -Path $adv -Name ShowSecondsInSystemClock -Type DWord -Value 0 -ErrorAction SilentlyContinue

  Write-Step 'Restarting Explorer'
  Stop-Process -Name explorer -Force -ErrorAction SilentlyContinue
  Start-Sleep -Milliseconds 800
  Start-Process explorer.exe

  Write-Host "Rollback complete." -ForegroundColor Green
}
catch {
  Write-Err $_.Exception.Message
  exit 1
}

