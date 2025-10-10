<#
  Rapihkan semua file di drive/folder secara rekursif dengan membuat subfolder kategori di setiap folder.
  - Aman: hanya memindahkan FILE di dalam tiap folder ke subfolder kategori di folder yang sama.
  - Tidak memindahkan FOLDER, dan melewati folder instalasi aplikasi (heuristik + daftar pengecualian).
  - Cocok untuk merapikan koleksi file tanpa merusak aplikasi terpasang.

  Contoh pakai:
    powershell -ExecutionPolicy Bypass -File "scripts\organize_recursive.ps1" -BasePath 'D:\'

  Opsi:
    -BasePath   : Folder dasar yang akan dirapikan (default: D:\)
    -DryRun     : Jika true, hanya simulasi (default: $false)
#>
[CmdletBinding()]
param(
  [string]$BasePath = 'D:\',
  [bool]$DryRun = $false
)

$ErrorActionPreference = 'Stop'

function Ensure-Dir([string]$p){ if(-not (Test-Path $p)){ New-Item -ItemType Directory -Force -Path $p | Out-Null } }

function Is-AppInstallDir([IO.DirectoryInfo]$dir){
  $name = $dir.Name.ToLowerInvariant()
  $path = $dir.FullName.ToLowerInvariant()
  $excludeNames = @(
    '$recycle.bin','system volume information','windows','program files',
    'program files (x86)','msdownld.tmp','ldplayer','winbox_windows',
    'games-kuyhaa','ollama','trae','warp'
  )
  foreach($ex in $excludeNames){ if($name -eq $ex){ return $true } }
  if($path -match 'program files|windows\\|\\driver|\\drivers|\\ldplayer|\\winbox') { return $true }
  # Jangan rapikan subfolder kategori kita sendiri
  if($name -match '^_{1,2}(docs|images|videos|audio|archives|apps|data|code|design|misc)$'){ return $true }
  # Heuristik: ada banyak .exe/dll dan struktur bin/lib/unins*
  try {
    $files = Get-ChildItem -LiteralPath $dir.FullName -File -ErrorAction SilentlyContinue
    $exeCount = ($files | Where-Object { $_.Extension -match '\.(exe|msi)$' }).Count
    $dllCount = ($files | Where-Object { $_.Extension -match '\.(dll)$' }).Count
    $hasUnins = ($files | Where-Object { $_.Name -match '^unins.*\.exe$' }).Count -gt 0
    $hasSetup = ($files | Where-Object { $_.Name -match 'setup.*\.exe$' }).Count -gt 0
    $hasBin = Test-Path (Join-Path $dir.FullName 'bin')
    $hasLib = Test-Path (Join-Path $dir.FullName 'lib')
    if($exeCount -ge 3 -or $dllCount -ge 10 -or $hasUnins -or $hasSetup -or $hasBin -or $hasLib){ return $true }
  } catch {}
  return $false
}

if(-not (Test-Path $BasePath)){
  Write-Host "BasePath tidak ditemukan: $BasePath" -ForegroundColor Red; exit 1
}

$categoryMap = @{
  'Docs'    = @('pdf','doc','docx','xls','xlsx','ppt','pptx','csv','txt','rtf');
  'Images'  = @('jpg','jpeg','png','gif','bmp','svg','webp','ico');
  'Videos'  = @('mp4','mkv','avi','mov','wmv','webm');
  'Audio'   = @('mp3','wav','flac','m4a','aac','ogg');
  'Archives'= @('zip','rar','7z','tar','gz','iso');
  'Apps'    = @('exe','msi','apk','bat','cmd');
  'Data'    = @('json','xml','yaml','yml','sql');
  'Code'    = @('ps1','sh','py','js','ts','php','html','css');
  'Design'  = @('psd','ai','cdr','xd','fig');
}

$moved = New-Object System.Collections.Generic.List[object]
$dirs = Get-ChildItem -LiteralPath $BasePath -Directory -Recurse -ErrorAction SilentlyContinue
# Sertakan basePath sendiri juga
$dirs = ,(Get-Item -LiteralPath $BasePath) + $dirs

foreach($dir in $dirs){
  if(Is-AppInstallDir $dir){ continue }
  # Ambil file DI DALAM folder ini saja
  $files = Get-ChildItem -LiteralPath $dir.FullName -File -ErrorAction SilentlyContinue
  if(-not $files){ continue }
  foreach($f in $files){
    # Lewati shortcut dan file kategori kita
    if($f.Extension -ieq '.lnk'){ continue }
    if($f.Directory.Name -match '^_{1,2}(docs|images|videos|audio|archives|apps|data|code|design|misc)$'){ continue }
    $ext = $f.Extension.TrimStart('.').ToLowerInvariant()
    $cat = 'Misc'
    foreach($k in $categoryMap.Keys){ if($categoryMap[$k] -contains $ext){ $cat=$k; break } }
    $destDir = Join-Path $dir.FullName ('__' + $cat)
    if(-not $DryRun){ Ensure-Dir $destDir }
    $destPath = Join-Path $destDir $f.Name
    $i=1
    while(Test-Path $destPath){
      $name=[IO.Path]::GetFileNameWithoutExtension($f.Name)
      $extDot=[IO.Path]::GetExtension($f.Name)
      $destPath = Join-Path $destDir ("{0} ({1}){2}" -f $name,$i,$extDot)
      $i++
    }
    if(-not $DryRun){ Move-Item -LiteralPath $f.FullName -Destination $destPath }
    $moved.Add([pscustomobject]@{ From=$f.FullName; To=$destPath }) | Out-Null
  }
}

$logDir = Join-Path $BasePath '_Organized'
Ensure-Dir $logDir
$log = Join-Path $logDir ('organize_recursive_' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.csv')
$moved | Export-Csv -NoTypeInformation -Path $log -Encoding UTF8

Write-Host ("Selesai. {0} file dipindahkan di {1} folder. Log: {2}" -f $moved.Count,$dirs.Count,$log) -ForegroundColor Green

