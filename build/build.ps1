param(
    [string] $Version = '1.5.0'
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$outputPath = Join-Path $PSScriptRoot ("plg_system_tagrouting-v{0}.zip" -f $Version)
$stagingPath = Join-Path ([System.IO.Path]::GetTempPath()) ("tagrouting-build-{0}" -f [guid]::NewGuid().ToString('N'))

try {
    New-Item -ItemType Directory -Path $stagingPath | Out-Null

    foreach ($directory in @('services', 'src', 'language')) {
        Copy-Item -LiteralPath (Join-Path $projectRoot $directory) -Destination $stagingPath -Recurse
    }

    foreach ($file in @('tagrouting.xml', 'README.md')) {
        Copy-Item -LiteralPath (Join-Path $projectRoot $file) -Destination $stagingPath
    }

    if (Test-Path -LiteralPath $outputPath) {
        Remove-Item -LiteralPath $outputPath -Force
    }

    # Compress-Archive writes backslashes into entry names on Windows. Some
    # Joomla/PHP ZIP stacks reject those paths before manifest discovery, so
    # create every entry explicitly with the ZIP-standard forward slash.
    Add-Type -AssemblyName System.IO.Compression
    $zipStream = [System.IO.File]::Open($outputPath, [System.IO.FileMode]::CreateNew)
    $archive = New-Object System.IO.Compression.ZipArchive(
        $zipStream,
        [System.IO.Compression.ZipArchiveMode]::Create,
        $false
    )

    try {
        foreach ($sourceFile in Get-ChildItem -LiteralPath $stagingPath -File -Recurse) {
            $relativePath = $sourceFile.FullName.Substring($stagingPath.Length).TrimStart('\', '/')
            $entryName = $relativePath.Replace('\', '/')
            $entry = $archive.CreateEntry($entryName, [System.IO.Compression.CompressionLevel]::Optimal)
            $entry.LastWriteTime = $sourceFile.LastWriteTime
            $inputStream = $sourceFile.OpenRead()
            $outputStream = $entry.Open()

            try {
                $inputStream.CopyTo($outputStream)
            }
            finally {
                $outputStream.Dispose()
                $inputStream.Dispose()
            }
        }
    }
    finally {
        $archive.Dispose()
        $zipStream.Dispose()
    }

    Write-Output $outputPath
}
finally {
    if (Test-Path -LiteralPath $stagingPath) {
        Remove-Item -LiteralPath $stagingPath -Recurse -Force
    }
}
