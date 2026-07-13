param(
    [switch] $KeepEnvironment
)

$ErrorActionPreference = 'Stop'

$composeFile = (Resolve-Path (Join-Path $PSScriptRoot '..\phase-001\compose.yaml')).Path
$packageScript = (Resolve-Path (Join-Path $PSScriptRoot '..\phase-001\package.ps1')).Path
$projectName = 'holiday-management-phase-002'
$baseUrl = 'http://localhost:18080/api/v1'
$credentials = 'admin:Phase001-Test-Password'
$phase1Package = Join-Path $env:TEMP 'holiday-management-phase-001-upgrade.zip'
$responseFile = Join-Path $env:TEMP ("holiday-management-phase-002-" + [guid]::NewGuid() + '.json')
$concurrentFiles = @()

function Invoke-EspoApi {
    param(
        [Parameter(Mandatory = $true)][string] $Method,
        [Parameter(Mandatory = $true)][string] $Path,
        [string] $Body,
        [int] $ExpectedStatus = 200
    )

    $arguments = @(
        '-sS', '-o', $responseFile, '-w', '%{http_code}',
        '-u', $credentials, '-H', 'Content-Type: application/json', '-X', $Method
    )

    if ($Body) {
        $arguments += @('--data-binary', $Body)
    }

    $arguments += "$baseUrl/$Path"
    $status = & curl.exe @arguments

    if ([int] $status -ne $ExpectedStatus) {
        $response = if (Test-Path $responseFile) { Get-Content -Raw $responseFile } else { '' }
        throw "API $Method $Path returned $status; expected $ExpectedStatus. $response"
    }

    if ((Test-Path $responseFile) -and (Get-Item $responseFile).Length -gt 0) {
        return Get-Content -Raw $responseFile | ConvertFrom-Json
    }

    return $null
}

function Assert-Equal {
    param($Actual, $Expected, [string] $Message)

    if ($Actual -ne $Expected) {
        throw "$Message Expected '$Expected', got '$Actual'."
    }
}

function Invoke-BulkValue {
    param(
        [string] $UserId,
        [double] $OpeningBalance,
        [string] $Key,
        [string] $ResetDate
    )

    $body = @{
        items = @(@{
            userId = $UserId
            annualEntitlement = 21.0
            openingBalance = $OpeningBalance
            nextResetDate = $ResetDate
            idempotencyKey = $Key
        })
    } | ConvertTo-Json -Depth 5 -Compress

    return Invoke-EspoApi -Method POST -Path 'HolidayManagement/bulkInitialize' -Body $body
}

try {
    if (Test-Path $phase1Package) { Remove-Item -LiteralPath $phase1Package -Force }
    git archive --format=zip "--output=$phase1Package" '92216d3:HolidayManagement'
    if ($LASTEXITCODE -ne 0) { throw 'Could not build the PHASE-001 upgrade fixture with git archive.' }

    $phase2Package = & $packageScript | Select-Object -Last 1
    $phase1Name = Split-Path $phase1Package -Leaf
    $phase2Name = Split-Path $phase2Package -Leaf

    docker compose -p $projectName -f $composeFile up -d --wait
    if ($LASTEXITCODE -ne 0) { throw 'Docker Compose startup failed.' }

    docker cp $phase1Package "${projectName}-espocrm-1:/tmp/$phase1Name"
    docker compose -p $projectName -f $composeFile exec -T espocrm bin/command extension "--file=/tmp/$phase1Name"
    if ($LASTEXITCODE -ne 0) { throw 'PHASE-001 installation failed.' }

    docker cp $phase2Package "${projectName}-espocrm-1:/tmp/$phase2Name"
    docker compose -p $projectName -f $composeFile exec -T espocrm bin/command extension "--file=/tmp/$phase2Name"
    if ($LASTEXITCODE -ne 0) { throw 'PHASE-002 upgrade failed.' }

    docker compose -p $projectName -f $composeFile exec -T espocrm bin/command rebuild
    if ($LASTEXITCODE -ne 0) { throw 'EspoCRM rebuild failed.' }

    $userBody = @{
        userName = 'phase002-employee'
        firstName = 'Phase'
        lastName = 'Two Employee'
        type = 'regular'
        isActive = $true
        password = 'Phase002-User-Password'
        passwordConfirm = 'Phase002-User-Password'
    } | ConvertTo-Json -Compress
    $employee = Invoke-EspoApi -Method POST -Path 'User' -Body $userBody

    $bulk = Invoke-BulkValue -UserId $employee.id -OpeningBalance 10.0 -Key 'phase2:init:10' -ResetDate '2027-01-01'
    $profileId = $bulk.list[0].profileId
    $grant = Invoke-EspoApi -Method POST -Path 'HolidayManagement/reset' -Body (@{
        profileId = $profileId
        idempotencyKey = 'phase2:reset:positive'
    } | ConvertTo-Json -Compress)
    Assert-Equal $grant.balance 31.0 'Positive carry-over reset failed.'

    Invoke-BulkValue -UserId $employee.id -OpeningBalance -5.0 -Key 'phase2:init:deficit' -ResetDate '2028-01-01' | Out-Null
    $deficit = Invoke-EspoApi -Method POST -Path 'HolidayManagement/reset' -Body (@{
        profileId = $profileId
        idempotencyKey = 'phase2:reset:deficit'
    } | ConvertTo-Json -Compress)
    Assert-Equal $deficit.balance 16.0 'Deficit carry-over reset failed.'

    Invoke-BulkValue -UserId $employee.id -OpeningBalance 80.0 -Key 'phase2:init:pending' -ResetDate '2029-01-01' | Out-Null
    $pending = Invoke-EspoApi -Method POST -Path 'HolidayManagement/reset' -Body (@{
        profileId = $profileId
        idempotencyKey = 'phase2:reset:pending'
    } | ConvertTo-Json -Compress)
    Assert-Equal $pending.balance 80.0 'Ineligible reset changed the balance.'
    Assert-Equal $pending.resetPending $true '80 + 21 must remain resetPending.'

    $correctionBody = @{
        profileId = $profileId
        delta = -11.0
        reason = 'Reach the approved automatic-reset threshold'
        idempotencyKey = 'phase2:correction:to-69'
    } | ConvertTo-Json -Compress
    $automatic = Invoke-EspoApi -Method POST -Path 'HolidayManagement/correct' -Body $correctionBody
    Assert-Equal $automatic.balance 90.0 '69.0 + 21 did not auto-apply the pending reset.'
    Assert-Equal $automatic.resetPending $false 'Applied reset remained pending.'

    $duplicate = Invoke-EspoApi -Method POST -Path 'HolidayManagement/correct' -Body $correctionBody
    Assert-Equal $duplicate.balance 90.0 'Duplicate correction changed the balance.'
    Assert-Equal $duplicate.duplicate $true 'Duplicate idempotency key was not reported.'

    foreach ($number in 1..2) {
        $bodyPath = Join-Path $env:TEMP "phase2-concurrent-$number.json"
        $outputPath = Join-Path $env:TEMP "phase2-concurrent-$number.out"
        $concurrentFiles += @($bodyPath, $outputPath)
        @{
            profileId = $profileId
            delta = 1.0
            reason = "Concurrent correction $number"
            idempotencyKey = "phase2:concurrent:$number"
        } | ConvertTo-Json -Compress | Set-Content -LiteralPath $bodyPath -NoNewline

        $process = Start-Process curl.exe -PassThru -NoNewWindow -RedirectStandardOutput $outputPath -ArgumentList @(
            '-sS', '-u', $credentials, '-H', 'Content-Type: application/json',
            '-X', 'POST', '--data-binary', "@$bodyPath", "$baseUrl/HolidayManagement/correct"
        )
        Set-Variable -Name "correctionProcess$number" -Value $process
    }

    $correctionProcess1.WaitForExit()
    $correctionProcess2.WaitForExit()
    Assert-Equal $correctionProcess1.ExitCode 0 'First concurrent correction failed.'
    Assert-Equal $correctionProcess2.ExitCode 0 'Second concurrent correction failed.'

    $profiles = Invoke-EspoApi -Method GET -Path 'HolidayManagement/profiles'
    $profile = $profiles.list | Where-Object { $_.profileId -eq $profileId }
    Assert-Equal $profile.balance 92.0 'Concurrent corrections lost or duplicated an update.'

    $forced = Invoke-EspoApi -Method POST -Path 'HolidayManagement/reset' -Body (@{
        profileId = $profileId
        idempotencyKey = 'phase2:reset:forced'
        force = $true
        reason = 'Approved administrator exception'
    } | ConvertTo-Json -Compress)
    Assert-Equal $forced.balance 113.0 'Reasoned forced reset failed.'

    $ledger = Invoke-EspoApi -Method GET -Path 'HolidayLedger?maxSize=100'
    $keys = @($ledger.list | ForEach-Object { $_.idempotencyKey })
    foreach ($key in @('phase2:init:10', 'phase2:reset:pending', 'phase2:correction:to-69', 'phase2:concurrent:1', 'phase2:concurrent:2', 'phase2:reset:forced')) {
        if ($keys -notcontains $key) { throw "HolidayLedger is missing audited operation $key." }
    }

    Write-Output 'PHASE-002 Docker upgrade/accounting/concurrency tests passed.'
}
finally {
    foreach ($file in @($responseFile, $phase1Package) + $concurrentFiles) {
        if (Test-Path $file) { Remove-Item -LiteralPath $file -Force }
    }

    if (-not $KeepEnvironment) {
        docker compose -p $projectName -f $composeFile down -v --remove-orphans
    }
}
