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

    Compress-Archive -Path (Join-Path $stagingRoot '*') -DestinationPath $OutputPath

    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $archive = [System.IO.Compression.ZipFile]::OpenRead($OutputPath)

    try {
        $entryNames = @($archive.Entries | ForEach-Object { $_.FullName.Replace('\', '/') })

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
