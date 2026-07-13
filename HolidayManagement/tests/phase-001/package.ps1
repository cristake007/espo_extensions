param(
    [string] $OutputPath
)

$ErrorActionPreference = 'Stop'

$extensionRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$repositoryRoot = (Resolve-Path (Join-Path $extensionRoot '..')).Path
$manifest = Get-Content -Raw (Join-Path $extensionRoot 'manifest.json') | ConvertFrom-Json

if (-not $OutputPath) {
    $OutputPath = Join-Path $repositoryRoot "dist\holiday-management-$($manifest.version).zip"
}

$OutputPath = [System.IO.Path]::GetFullPath($OutputPath)
$stagingRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("holiday-management-package-" + [guid]::NewGuid())

try {
    New-Item -ItemType Directory -Path $stagingRoot | Out-Null
    New-Item -ItemType Directory -Path (Split-Path $OutputPath -Parent) -Force | Out-Null

    Copy-Item (Join-Path $extensionRoot 'manifest.json') $stagingRoot
    Copy-Item (Join-Path $extensionRoot 'README.md') $stagingRoot
    Copy-Item (Join-Path $extensionRoot 'files') $stagingRoot -Recurse
    Copy-Item (Join-Path $extensionRoot 'scripts') $stagingRoot -Recurse

    if (Test-Path $OutputPath) {
        Remove-Item -LiteralPath $OutputPath
    }

    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $archive = [System.IO.Compression.ZipFile]::Open(
        $OutputPath,
        [System.IO.Compression.ZipArchiveMode]::Create
    )

    try {
        foreach ($file in Get-ChildItem -LiteralPath $stagingRoot -Recurse -File) {
            $relativePath = $file.FullName.Substring($stagingRoot.Length).TrimStart('\', '/')
            $entryName = $relativePath.Replace('\', '/')

            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                $archive,
                $file.FullName,
                $entryName,
                [System.IO.Compression.CompressionLevel]::Optimal
            ) | Out-Null
        }
    }
    finally {
        $archive.Dispose()
    }

    $archive = [System.IO.Compression.ZipFile]::OpenRead($OutputPath)

    try {
        if ($archive.Entries | Where-Object { $_.FullName -match '\\' }) {
            throw 'The ZIP contains non-portable backslash entry names.'
        }

        $entryNames = @($archive.Entries | ForEach-Object { $_.FullName })

        if ($entryNames -notcontains 'manifest.json') {
            throw 'Package manifest.json is not at the ZIP root.'
        }

        if ($entryNames | Where-Object { $_ -like 'HolidayManagement/*' }) {
            throw 'The ZIP incorrectly contains a HolidayManagement parent directory.'
        }

        if (-not ($entryNames | Where-Object { $_ -like 'files/*' })) {
            throw 'The ZIP does not contain extension files.'
        }

        if (-not ($entryNames | Where-Object { $_ -like 'scripts/*' })) {
            throw 'The ZIP does not contain install scripts.'
        }
    }
    finally {
        $archive.Dispose()
    }
}
finally {
    if (Test-Path $stagingRoot) {
        Remove-Item -LiteralPath $stagingRoot -Recurse -Force
    }
}

Write-Output $OutputPath
