<#
  Rapihkan file di root drive D: ke dalam folder kategori D:\_Organized
  Aman: hanya memindahkan FILE di root D:, tidak memindahkan folder aplikasi/projek.
  Kategori: Documents, Images, Videos, Audio, Archives, Apps, Data, Misc

  Jalankan (PowerShell biasa):
    powershell -ExecutionPolicy Bypass -File "scripts\organize_drive_d.ps1"
#>
[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'

function Ensure-Dir([string]$p){ if(-not (Test-Path $p)){ New-Item -ItemType Directory -Force -Path $p | Out-Null } }

$base = 'D:\'
if(-not (Test-Path $base)){ Write-Host 'Drive D: tidak ditemukan.' -ForegroundColor Red; exit 1 }

$rootFiles = Get-ChildItem -LiteralPath $base -File -ErrorAction SilentlyContinue
if(-not $rootFiles){ Write-Host 'Tidak ada file di root D: yang perlu dirapikan.' -ForegroundColor Yellow; exit 0 }

$org = Join-Path $base '_Organized'
Ensure-Dir $org
$map = @{
  'Documents' = @('pdf','doc','docx','xls','xlsx','ppt','pptx','csv','txt');
  'Images'    = @('jpg','jpeg','png','gif','bmp','svg','webp','ico');
  'Videos'    = @('mp4','mkv','avi','mov','wmv');
  'Audio'     = @('mp3','wav','flac','m4a','aac','ogg');
  'Archives'  = @('zip','rar','7z','tar','gz','iso');
  'Apps'      = @('exe','msi','apk');
  'Data'      = @('json','xml','yaml','yml','sql');
}

$moved = @()
foreach($f in $rootFiles){
  $ext = ($f.Extension.TrimStart('.')).ToLowerInvariant()
  $targetFolder = $null
  foreach($k in $map.Keys){ if($map[$k] -contains $ext){ $targetFolder = $k; break } }
  if(-not $targetFolder){ $targetFolder = 'Misc' }
  $destDir = Join-Path $org $targetFolder
  Ensure-Dir $destDir
  $destPath = Join-Path $destDir $f.Name
  $i = 1
  while(Test-Path $destPath){
    $name = [IO.Path]::GetFileNameWithoutExtension($f.Name)
    $extDot = [IO.Path]::GetExtension($f.Name)
    $destPath = Join-Path $destDir ("{0} ({1}){2}" -f $name,$i,$extDot)
    $i++
  }
  Move-Item -LiteralPath $f.FullName -Destination $destPath
  $moved += [pscustomobject]@{ From=$f.FullName; To=$destPath }
}

$log = Join-Path $org ('_organize_log_' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.csv')
$moved | Export-Csv -NoTypeInformation -Path $log -Encoding UTF8
Write-Host ("Selesai. {0} file dipindahkan ke {1}" -f $moved.Count,$org) -ForegroundColor Green
Write-Host ("Log: " + $log)

