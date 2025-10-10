<#
  KDE-like Dark theming for Windows (safe + reversible)
  - Installs ExplorerPatcher (taskbar/titlebar control)
  - Installs SecureUxTheme (theme engine)
  - Downloads a Dark KDE/Breeze theme pack and applies it
  - Sets compact/file visibility and taskbar tweaks (HKCU)

  Run as Administrator:
    powershell -ExecutionPolicy Bypass -File "<this-file>"

  Rollback:
    powershell -ExecutionPolicy Bypass -File "scripts\kde_dark_rollback.ps1"
#>
[CmdletBinding()]
param(
  [string]$ThemeZip = $null,
  [string]$ThemeUrl = $null
)

function Write-Step($msg){ Write-Host "[+] $msg" -ForegroundColor Cyan }
function Write-Warn($msg){ Write-Host "[!] $msg" -ForegroundColor Yellow }
function Write-Err($msg){ Write-Host "[x] $msg" -ForegroundColor Red }

try {
  $ErrorActionPreference = 'Stop'
  [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
  $UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'

  Write-Step 'Creating restore point (best-effort)'
  try { Checkpoint-Computer -Description 'PRE_KDE_THEME' -RestorePointType 'MODIFY_SETTINGS' } catch { Write-Warn $_.Exception.Message }

  function Get-LatestAssetUrl($owner,$repo,$pattern='\.exe$'){
    $h = @{ 'User-Agent' = $UA }
    $assets = @()
    # Try latest first
    try {
      $rel = Invoke-RestMethod "https://api.github.com/repos/$owner/$repo/releases/latest" -Headers $h -ErrorAction Stop
      if($rel -and $rel.assets){ $assets += $rel.assets }
    } catch {}
    # Fall back to recent releases (include pre-releases)
    if(-not $assets){
      try {
        $rels = Invoke-RestMethod "https://api.github.com/repos/$owner/$repo/releases?per_page=10" -Headers $h -ErrorAction Stop
        if($rels){ $rels | ForEach-Object { if($_.assets){ $assets += $_.assets } } }
      } catch {}
    }
    # Pick first matching asset
    $hit = $assets | Where-Object { $_.name -match $pattern } | Select-Object -First 1
    if($hit){ return $hit.browser_download_url }
    # HTML fallback (when API blocked or assets empty)
    try {
      $html = Invoke-WebRequest "https://github.com/$owner/$repo/releases" -Headers $h -UseBasicParsing
      $link = ($html.Links | Where-Object { $_.href -match '/download/.+\.exe$' } | Select-Object -First 1).href
      if($link){
        if($link -notmatch '^https?://'){ return 'https://github.com' + $link } else { return $link }
      }
    } catch {}
    return $null
  }

  function Resolve-ThemeZip {
    param(
      [string]$ThemeZipParam,
      [string]$ThemeUrlParam
    )
    if($ThemeZipParam -and (Test-Path $ThemeZipParam)){
      return (Resolve-Path $ThemeZipParam).Path
    }
    if($ThemeUrlParam){
      try{
        $tmp = Join-Path $env:TEMP 'ThemePack.zip'
        Invoke-WebRequest $ThemeUrlParam -OutFile $tmp -UseBasicParsing -Headers @{ 'User-Agent'=$UA }
        return $tmp
      } catch {}
    }
    # Try GitHub releases (niivu/Windows-11-Themes)
    $url = Get-LatestAssetUrl 'niivu' 'Windows-11-Themes' '\.zip$'
    if($url){
      $tmp = Join-Path $env:TEMP 'Win11Themes.zip'
      Invoke-WebRequest $url -OutFile $tmp -UseBasicParsing -Headers @{ 'User-Agent'=$UA }
      return $tmp
    }
    # Fallback: scrape HTML page
    try {
      $html = Invoke-WebRequest 'https://github.com/niivu/Windows-11-Themes/releases' -Headers @{ 'User-Agent'=$UA } -UseBasicParsing
      $href = ($html.Links | Where-Object { $_.href -match '/download/.+\.zip$' } | Select-Object -First 1).href
      if($href){
        $full = ($href -match '^https?://') ? $href : ('https://github.com' + $href)
        $tmp = Join-Path $env:TEMP 'Win11Themes.zip'
        Invoke-WebRequest $full -OutFile $tmp -UseBasicParsing -Headers @{ 'User-Agent'=$UA }
        return $tmp
      }
    } catch {}
    # Local fallback: cari di Downloads user
    $dl = Join-Path $env:USERPROFILE 'Downloads'
    if(Test-Path $dl){
      $pick = Get-ChildItem $dl -Filter '*.zip' -ErrorAction SilentlyContinue | Where-Object { $_.Name -match '(?i)breeze|kde|theme' } | Sort-Object LastWriteTime -Descending | Select-Object -First 1
      if($pick){ return $pick.FullName }
    }
    return $null
  }

  # 1) ExplorerPatcher
  Write-Step 'Installing ExplorerPatcher'
  $epUrl = 'https://github.com/valinet/ExplorerPatcher/releases/latest/download/ep_setup.exe'
  $ep = Join-Path $env:TEMP 'ep_setup.exe'
  Invoke-WebRequest $epUrl -OutFile $ep -UseBasicParsing -Headers @{ 'User-Agent'=$UA }
  Start-Process $ep -ArgumentList '/verysilent' -Wait

  # 2) SecureUxTheme
  Write-Step 'Installing SecureUxTheme'
  # Prefer MSI bernama SecureUxTheme_x64.msi; cek lokal dulu
  $dlDir = Join-Path $env:USERPROFILE 'Downloads'
  $localMsiCandidates = @(
    (Join-Path $PSScriptRoot 'SecureUxTheme_x64.msi'),
    (Join-Path $dlDir 'SecureUxTheme_x64.msi')
  )
  $localMsi = $localMsiCandidates | Where-Object { Test-Path $_ } | Select-Object -First 1
  if(-not $localMsi){
    $localMsi = Get-ChildItem -Path $dlDir -Filter 'SecureUxTheme*.msi' -ErrorAction SilentlyContinue | Select-Object -ExpandProperty FullName -First 1
  }
  if($localMsi){
    Write-Step "Using local MSI: $localMsi"
    Start-Process 'msiexec.exe' -ArgumentList @('/i', $localMsi, '/qn', '/norestart') -Wait
  } else {
    # Cari dari rilis terbaru (prefer nama SecureUxTheme_x64.msi)
    $assetMsi = Get-LatestAssetUrl 'namazso' 'SecureUxTheme' '(?i)SecureUxTheme_x64\.msi$'
    if(-not $assetMsi){ $assetMsi = Get-LatestAssetUrl 'namazso' 'SecureUxTheme' '(?i)SecureUxTheme.*\.msi$|\.msi$' }
    $assetExe = $null
    if(-not $assetMsi){ $assetExe = Get-LatestAssetUrl 'namazso' 'SecureUxTheme' '(?i)setup.*\.exe$|(?i)SecureUxTheme.*\.exe$|\.exe$' }
    if($assetMsi){
      $sutMsi = Join-Path $env:TEMP 'SecureUxTheme_x64.msi'
      Invoke-WebRequest $assetMsi -OutFile $sutMsi -UseBasicParsing -Headers @{ 'User-Agent'=$UA }
      Start-Process 'msiexec.exe' -ArgumentList @('/i', $sutMsi, '/qn', '/norestart') -Wait
    } elseif ($assetExe) {
      $sutExe = Join-Path $env:TEMP 'SecureUxTheme_setup.exe'
      Invoke-WebRequest $assetExe -OutFile $sutExe -UseBasicParsing -Headers @{ 'User-Agent'=$UA }
      Start-Process $sutExe -ArgumentList '/VERYSILENT /SUPPRESSMSGBOXES /NORESTART' -Wait
    } else {
      Write-Warn 'MSI/EXE tidak ditemukan, mencoba fallback ZIP untuk SecureUxTheme'
      $zipUrl = Get-LatestAssetUrl 'namazso' 'SecureUxTheme' '\.zip$'
      if(-not $zipUrl){ throw 'Tidak menemukan installer SecureUxTheme (MSI/EXE/ZIP).' }
      $zip = Join-Path $env:TEMP 'SecureUxTheme.zip'
      Invoke-WebRequest $zipUrl -OutFile $zip -UseBasicParsing -Headers @{ 'User-Agent'=$UA }
      Add-Type -AssemblyName System.IO.Compression.FileSystem
      $zExtract = Join-Path $env:TEMP 'SecureUxTheme_extract'
      if(Test-Path $zExtract){ Remove-Item -Recurse -Force $zExtract }
      [IO.Compression.ZipFile]::ExtractToDirectory($zip,$zExtract)
      $found = Get-ChildItem $zExtract -Recurse -Include 'SecureUxTheme_x64.msi','*.msi','*setup*.exe','*SecureUxTheme*.exe','*.exe' | Select-Object -First 1
      if(-not $found){ throw 'ZIP didownload tetapi tidak ada installer EXE/MSI di dalamnya.' }
      if($found.Extension -ieq '.msi'){
        Start-Process 'msiexec.exe' -ArgumentList @('/i', $found.FullName, '/qn', '/norestart') -Wait
      } else {
        Start-Process $found.FullName -ArgumentList '/VERYSILENT /SUPPRESSMSGBOXES /NORESTART' -Wait
      }
    }
  }

  # 3) Theme pack (Breeze/KDE)
  Write-Step 'Resolving KDE/Breeze Dark theme pack (.zip)'
  $themeZipPath = Resolve-ThemeZip -ThemeZipParam $ThemeZip -ThemeUrlParam $ThemeUrl
  if(-not $themeZipPath){ throw 'Tidak menemukan paket tema .zip (online/lokal). Unduh manual pack Breeze/KDE untuk Windows 11 lalu jalankan ulang dengan parameter -ThemeZip "C:\path\to\pack.zip".' }

  Add-Type -AssemblyName System.IO.Compression.FileSystem
  $extract = Join-Path $env:TEMP 'themes_extract'
  if(Test-Path $extract){ Remove-Item -Recurse -Force $extract }
  [IO.Compression.ZipFile]::ExtractToDirectory($themeZipPath, $extract)

  $themesDest = 'C:\Windows\Resources\Themes'
  Write-Step "Copying theme files to $themesDest"
  Copy-Item (Join-Path $extract '*') $themesDest -Recurse -Force

  # 4) Apply a Dark theme
  $dark = Get-ChildItem $themesDest -Filter '*Breeze*Dark*.theme','*KDE*Dark*.theme','*Dark*.theme' -ErrorAction SilentlyContinue | Select-Object -First 1
  if($dark){
    Write-Step "Applying theme: $($dark.Name)"
    Start-Process $dark.FullName
  } else {
    Write-Warn 'No Dark .theme detected automatically. Pick one in Settings > Personalization > Themes.'
  }

  # 5) KDE-like Explorer and taskbar tweaks (HKCU, safe)
  Write-Step 'Applying KDE-like Explorer/taskbar tweaks'
  $adv='HKCU:\Software\Microsoft\Windows\CurrentVersion\Explorer\Advanced'
  New-Item -Path $adv -Force | Out-Null
  Set-ItemProperty -Path $adv -Name UseCompactMode -Type DWord -Value 1
  Set-ItemProperty -Path $adv -Name HideFileExt -Type DWord -Value 0
  Set-ItemProperty -Path $adv -Name Hidden -Type DWord -Value 1
  Set-ItemProperty -Path $adv -Name TaskbarSi -Type DWord -Value 0      # small
  Set-ItemProperty -Path $adv -Name TaskbarAl -Type DWord -Value 1      # left aligned
  Set-ItemProperty -Path $adv -Name ShowSecondsInSystemClock -Type DWord -Value 1
  $pers='HKCU:\Software\Microsoft\Windows\CurrentVersion\Themes\Personalize'
  New-Item -Path $pers -Force | Out-Null
  Set-ItemProperty -Path $pers -Name AppsUseLightTheme -Type DWord -Value 0
  Set-ItemProperty -Path $pers -Name SystemUsesLightTheme -Type DWord -Value 0

  # 6) Restart Explorer
  Write-Step 'Restarting Explorer'
  Stop-Process -Name explorer -Force -ErrorAction SilentlyContinue
  Start-Sleep -Milliseconds 800
  Start-Process explorer.exe

  Write-Host "\nDone. Open taskbar Properties (ExplorerPatcher) to fine-tune: Small icons, Left, Combine=Never; Tray clock seconds ON." -ForegroundColor Green
}
catch {
  Write-Err $_.Exception.Message
  exit 1
}
