Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$packageRoot = Join-Path $repoRoot 'package'
$packageManifest = Join-Path $packageRoot 'pkg_smarttag.xml'
$pluginRoot = Join-Path $packageRoot 'plugins\system\tagrouting'
$fieldPluginRoot = Join-Path $packageRoot 'plugins\fields\tagselect'
$componentRoot = Join-Path $packageRoot 'components\com_tagrouting'
$buildRoot = Join-Path $repoRoot 'build'
$stageRoot = Join-Path $buildRoot 'stage'
$outputRoot = Join-Path $buildRoot 'output'

function Reset-Directory {
    param([Parameter(Mandatory = $true)][string] $Path)

    if (Test-Path -LiteralPath $Path) {
        Remove-Item -LiteralPath $Path -Recurse -Force
    }

    New-Item -ItemType Directory -Path $Path | Out-Null
}

function Get-ManifestVersion {
    param([Parameter(Mandatory = $true)][string] $Path)

    [xml] $manifest = Get-Content -LiteralPath $Path -Raw
    $versionNode = $manifest.extension.SelectSingleNode('version')
    $version = if ($null -ne $versionNode) { $versionNode.InnerText } else { $null }

    if ([string]::IsNullOrWhiteSpace($version)) {
        throw "Manifest version missing: $Path"
    }

    return $version.Trim()
}

function New-CompatibleZip {
    param(
        [Parameter(Mandatory = $true)][string] $SourceDirectory,
        [Parameter(Mandatory = $true)][string] $DestinationZip
    )

    if (Test-Path -LiteralPath $DestinationZip) {
        Remove-Item -LiteralPath $DestinationZip -Force
    }

    Add-Type -AssemblyName System.IO.Compression
    $stream = [System.IO.File]::Open($DestinationZip, [System.IO.FileMode]::CreateNew)

    try {
        $archive = New-Object System.IO.Compression.ZipArchive(
            $stream,
            [System.IO.Compression.ZipArchiveMode]::Create,
            $false
        )

        try {
            $root = [System.IO.Path]::GetFullPath($SourceDirectory)

            foreach ($sourceFile in Get-ChildItem -LiteralPath $SourceDirectory -File -Recurse) {
                $relative = $sourceFile.FullName.Substring($root.Length).TrimStart('\', '/')
                $entryName = $relative.Replace('\', '/')
                $entry = $archive.CreateEntry($entryName, [System.IO.Compression.CompressionLevel]::Optimal)
                $entry.LastWriteTime = $sourceFile.LastWriteTime
                $input = $sourceFile.OpenRead()
                $output = $entry.Open()

                try {
                    $input.CopyTo($output)
                }
                finally {
                    $output.Dispose()
                    $input.Dispose()
                }
            }
        }
        finally {
            $archive.Dispose()
        }
    }
    finally {
        $stream.Dispose()
    }
}

foreach ($requiredPath in @($packageManifest, $pluginRoot, $fieldPluginRoot, $componentRoot)) {
    if (-not (Test-Path -LiteralPath $requiredPath)) {
        throw "Required package source missing: $requiredPath"
    }
}

$packageVersion = Get-ManifestVersion -Path $packageManifest
$pluginVersion = Get-ManifestVersion -Path (Join-Path $pluginRoot 'tagrouting.xml')
$fieldPluginVersion = Get-ManifestVersion -Path (Join-Path $fieldPluginRoot 'tagselect.xml')
$componentVersion = Get-ManifestVersion -Path (Join-Path $componentRoot 'tagrouting.xml')

if ($packageVersion -ne $pluginVersion) {
    throw "Package and routing plugin versions differ: package=$packageVersion plugin=$pluginVersion"
}

Reset-Directory -Path $stageRoot
Reset-Directory -Path $outputRoot
$constituentRoot = Join-Path $stageRoot 'constituents'
$packageStage = Join-Path $stageRoot 'package'
$packageFiles = Join-Path $packageStage 'packages'
New-Item -ItemType Directory -Path $constituentRoot, $packageFiles | Out-Null

$pluginZip = Join-Path $constituentRoot 'plg_system_tagrouting.zip'
$fieldPluginZip = Join-Path $constituentRoot 'plg_fields_tagselect.zip'
$componentZip = Join-Path $constituentRoot 'com_tagrouting.zip'
New-CompatibleZip -SourceDirectory $pluginRoot -DestinationZip $pluginZip
New-CompatibleZip -SourceDirectory $fieldPluginRoot -DestinationZip $fieldPluginZip
New-CompatibleZip -SourceDirectory $componentRoot -DestinationZip $componentZip

Copy-Item -LiteralPath $packageManifest -Destination $packageStage
Copy-Item -LiteralPath $pluginZip -Destination $packageFiles
Copy-Item -LiteralPath $fieldPluginZip -Destination $packageFiles
Copy-Item -LiteralPath $componentZip -Destination $packageFiles

$packageLanguage = Join-Path $packageRoot 'language'

if (Test-Path -LiteralPath $packageLanguage) {
    Copy-Item -LiteralPath $packageLanguage -Destination $packageStage -Recurse
}

$packageZip = Join-Path $outputRoot ("pkg_smarttag_v{0}.zip" -f $packageVersion)
New-CompatibleZip -SourceDirectory $packageStage -DestinationZip $packageZip

Write-Output ("Child versions: routing={0}, tag-select={1}, context-component={2}" -f $pluginVersion, $fieldPluginVersion, $componentVersion)
Write-Output $packageZip
