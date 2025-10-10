<#
  Rename seluruh folder agar rapi & konsisten (Title Case) kecuali folder instalasi aplikasi.
  - Berlaku rekursif mulai dari BasePath (default: D:\)
  - Melewati folder: Program Files, Windows, System Volume Information, $RECYCLE.BIN, msdownld.tmp, dsb
  - Melewati folder yang terdeteksi sebagai folder instalasi aplikasi (heuristik: banyak .exe/.dll, ada bin/lib, unins*.exe)
  - Membuat log CSV untuk semua perubahan sehingga mudah rollback manual

  Jalankan:
    powershell -ExecutionPolicy Bypass -File "scripts\rename_folders.ps1" -BasePath 'D:\'
#>
[CmdletBinding()]
param(
  [string]$BasePath = 'D:\'
)

$ErrorActionPreference = 'Stop'
if(-not (Test-Path $BasePath)){
  Write-Host "BasePath tidak ditemukan: $BasePath" -ForegroundColor Red; exit 1
}

function Is-SystemOrExcluded([IO.DirectoryInfo]$dir){
  $name = $dir.Name.ToLowerInvariant()
  $path = $dir.FullName.ToLowerInvariant()
  $attr = $dir.Attributes
  if($attr -band [IO.FileAttributes]::System -or $attr -band [IO.FileAttributes]::ReparsePoint){ return $true }
  $ex = @('$recycle.bin','system volume information','windows','program files','program files (x86)','msdownld.tmp')
  if($ex -contains $name){ return $true }
  return $false
}

function Is-AppInstallDir([IO.DirectoryInfo]$dir){
  $name = $dir.Name.ToLowerInvariant()
  $path = $dir.FullName.ToLowerInvariant()
  # Daftar pengecualian spesifik (aman)
  $soft = @('ldplayer','winbox_windows','games-kuyhaa','ollama','warp','trae')
  if($soft -contains $name){ return $true }
  if($path -match 'program files|windows\\|\\drivers|\\ldplayer|\\winbox'){ return $true }
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

function Normalize-Name([string]$name){
  $orig = $name
  $s = $name -replace '[_\-]+',' ' -replace '\s+',' ' -replace '\.',' '
  $s = $s.Trim()
  if([string]::IsNullOrWhiteSpace($s)){ return $orig }
  $tokens = $s -split ' '
  $acronyms = @('AI','APK','SQL','PDF','CSV','API','DB','UI','UX','LED','LAN','WAN','OS','VM','XR','AR','VR')
  $result = New-Object System.Collections.Generic.List[string]
  foreach($t in $tokens){
    if($t -match '^[0-9]+$'){ $result.Add($t); continue }
    $up = $t.ToUpperInvariant()
    if($acronyms -contains $up -and $t.Length -le 4){ $result.Add($up); continue }
    # TitleCase per token (id-ID)
    $ti = (Get-Culture).TextInfo
    $result.Add($ti.ToTitleCase($t.ToLower()))
  }
  $out = ($result -join ' ').Trim()
  return $out
}

# Kumpulkan semua folder (deep) dan urutkan dari terdalam agar aman saat rename
$dirs = Get-ChildItem -LiteralPath $BasePath -Directory -Recurse -ErrorAction SilentlyContinue | Sort-Object { $_.FullName.Length } -Descending
$changes = New-Object System.Collections.Generic.List[object]

foreach($d in $dirs){
  if(Is-SystemOrExcluded $d){ continue }
  if(Is-AppInstallDir $d){ continue }
  $parent = Split-Path $d.FullName -Parent
  $newName = Normalize-Name $d.Name
  if([string]::IsNullOrWhiteSpace($newName) -or $newName -eq $d.Name){ continue }
  $target = Join-Path $parent $newName
  # Pastikan tidak tabrakan
  $i=1; $final=$target
  while(Test-Path $final){ $final = Join-Path $parent ("{0} ({1})" -f $newName,$i); $i++ }
  try {
    Rename-Item -LiteralPath $d.FullName -NewName (Split-Path $final -Leaf)
    $changes.Add([pscustomobject]@{ From=$d.FullName; To=$final }) | Out-Null
  } catch {
    Write-Host "Lewati: $($d.FullName) -> $newName : $($_.Exception.Message)" -ForegroundColor Yellow
  }
}

# Rapikan isi D:\Downloads menggunakan kategori di tempat yang sama (tanpa memindahkan folder)
$downloads = Join-Path $BasePath 'Downloads'
if(Test-Path $downloads){
  $catMap = @{
    'Docs'    = @('pdf','doc','docx','xls','xlsx','ppt','pptx','csv','txt','rtf');
    'Images'  = @('jpg','jpeg','png','gif','bmp','svg','webp','ico');
    'Videos'  = @('mp4','mkv','avi','mov','wmv','webm');
    'Audio'   = @('mp3','wav','flac','m4a','aac','ogg');
    'Archives'= @('zip','rar','7z','tar','gz','iso');
    'Apps'    = @('exe','msi','apk');
    'Data'    = @('json','xml','yaml','yml','sql');
    'Code'    = @('ps1','sh','py','js','ts','php','html','css');
  }
  $files = Get-ChildItem -LiteralPath $downloads -File -ErrorAction SilentlyContinue
  foreach($f in $files){
    $ext = $f.Extension.TrimStart('.').ToLowerInvariant()
    $cat='Misc'
    foreach($k in $catMap.Keys){ if($catMap[$k] -contains $ext){ $cat=$k; break } }
    $destDir = Join-Path $downloads ('__' + $cat)
    if(-not (Test-Path $destDir)){ New-Item -ItemType Directory -Force -Path $destDir | Out-Null }
    $dest = Join-Path $destDir $f.Name
    $i=1; while(Test-Path $dest){ $dest = Join-Path $destDir ("{0} ({1}){2}" -f ([IO.Path]::GetFileNameWithoutExtension($f.Name)),$i,[IO.Path]::GetExtension($f.Name)); $i++ }
    Move-Item -LiteralPath $f.FullName -Destination $dest
    $changes.Add([pscustomobject]@{ From=$f.FullName; To=$dest }) | Out-Null
  }
}

# Simpan log perubahan
$logDir = Join-Path $BasePath '_Organized'
if(-not (Test-Path $logDir)){ New-Item -ItemType Directory -Force -Path $logDir | Out-Null }
$log = Join-Path $logDir ('rename_folders_' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.csv')
$changes | Export-Csv -NoTypeInformation -Path $log -Encoding UTF8
Write-Host ("Selesai rename & rapihkan. Perubahan: {0}. Log: {1}" -f $changes.Count,$log) -ForegroundColor Green

