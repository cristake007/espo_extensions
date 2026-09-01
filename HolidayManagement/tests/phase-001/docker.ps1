param(
    [switch] $KeepEnvironment
)

$ErrorActionPreference = 'Stop'

$composeFile = Join-Path $PSScriptRoot 'compose.yaml'
$projectName = 'holiday-management-phase-001'
$baseUrl = 'http://localhost:18080/api/v1'
$credentials = 'admin:Phase001-Test-Password'
$extensionRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$responseFile = Join-Path ([System.IO.Path]::GetTempPath()) ("holiday-management-response-" + [guid]::NewGuid() + '.json')

function Invoke-EspoApi {
    param(
        [Parameter(Mandatory = $true)][string] $Method,
        [Parameter(Mandatory = $true)][string] $Path,
        [string] $Body,
        [int] $ExpectedStatus = 200
    )

    $arguments = @(
        '-sS',
        '-o', $responseFile,
        '-w', '%{http_code}',
        '-u', $credentials,
        '-H', 'Content-Type: application/json',
        '-X', $Method
    )

    if ($Body) {
        $arguments += @('--data-binary', $Body)
    }

    $arguments += "$baseUrl/$Path"
    $status = & curl.exe @arguments

    if ([int]$status -ne $ExpectedStatus) {
        $response = if (Test-Path $responseFile) { Get-Content -Raw $responseFile } else { '' }
        throw "API $Method $Path returned $status; expected $ExpectedStatus. $response"
    }

    if ((Test-Path $responseFile) -and (Get-Item $responseFile).Length -gt 0) {
        return Get-Content -Raw $responseFile | ConvertFrom-Json
    }

    return $null
}

function Assert-Equal {
    param(
        [Parameter(Mandatory = $true)][AllowNull()] $Actual,
        [Parameter(Mandatory = $true)][AllowNull()] $Expected,
        [Parameter(Mandatory = $true)][string] $Message
    )

    if ($Actual -ne $Expected) {
        throw "$Message Expected '$Expected', got '$Actual'."
    }
}

try {
    $packagePath = & (Join-Path $PSScriptRoot 'package.ps1') | Select-Object -Last 1
    $packageName = Split-Path $packagePath -Leaf

    docker compose -p $projectName -f $composeFile up -d --wait
    if ($LASTEXITCODE -ne 0) { throw 'Docker Compose startup failed.' }

    docker cp $packagePath "${projectName}-espocrm-1:/tmp/$packageName"
    if ($LASTEXITCODE -ne 0) { throw 'Could not copy extension package into EspoCRM.' }

    docker cp (Join-Path $extensionRoot 'scripts\AfterInstall.php') "${projectName}-espocrm-1:/tmp/HolidayManagement-AfterInstall.php"
    if ($LASTEXITCODE -ne 0) { throw 'Could not copy the install script for syntax validation.' }

    docker compose -p $projectName -f $composeFile exec -T espocrm bin/command extension "--file=/tmp/$packageName"
    if ($LASTEXITCODE -ne 0) { throw 'EspoCRM extension installation failed.' }

    docker compose -p $projectName -f $composeFile exec -T espocrm bin/command rebuild
    if ($LASTEXITCODE -ne 0) { throw 'EspoCRM rebuild failed.' }

    docker compose -p $projectName -f $composeFile exec -T espocrm php -l /tmp/HolidayManagement-AfterInstall.php
    docker compose -p $projectName -f $composeFile exec -T espocrm php -l custom/Espo/Modules/HolidayManagement/FieldValidators/Settings/Approvers/Valid.php
    if ($LASTEXITCODE -ne 0) { throw 'PHP syntax validation failed.' }

    $metadata = Invoke-EspoApi -Method GET -Path 'Metadata'
    if (-not $metadata.app.adminPanel.holidayManagement) {
        throw 'Holiday Management is missing from Administration metadata.'
    }

    $settings = Invoke-EspoApi -Method GET -Path 'Settings'
    Assert-Equal $settings.holidayManagementAnnualEntitlementDays $null 'Annual entitlement must be configured explicitly.'
    Assert-Equal $settings.holidayManagementResetDate $null 'Reset date must be configured explicitly.'
    Assert-Equal $settings.holidayManagementCarryOverLimitDays 90 'Carry-over limit default mismatch.'
    Assert-Equal $settings.holidayManagementNegativeBalanceLimitDays -21 'Negative limit default mismatch.'

    $persistedTitle = 'Director'
    $persistedName = 'Test Name'
    $update = @{
        holidayManagementApprovalBlock1Title = $persistedTitle
        holidayManagementApprovalBlock1Name = $persistedName
    } | ConvertTo-Json -Compress
    Invoke-EspoApi -Method PUT -Path 'Settings' -Body $update | Out-Null

    $settings = Invoke-EspoApi -Method GET -Path 'Settings'
    Assert-Equal $settings.holidayManagementApprovalBlock1Title $persistedTitle 'Printed title did not persist.'
    Assert-Equal $settings.holidayManagementApprovalBlock1Name $persistedName 'Printed name did not persist.'

    $userIds = @()
    $userNames = @{}

    foreach ($number in 1..3) {
        $userBody = @{
            userName = "phase001-approver-$number"
            firstName = 'Phase'
            lastName = "Approver $number"
            type = 'regular'
            isActive = $true
            password = 'Phase001-User-Password'
            passwordConfirm = 'Phase001-User-Password'
        } | ConvertTo-Json -Compress

        $user = Invoke-EspoApi -Method POST -Path 'User' -Body $userBody
        $userIds += $user.id
        $userNames[$user.id] = $user.name
    }

    $threeApprovers = @{
        holidayManagementApproversIds = $userIds
        holidayManagementApproversNames = $userNames
    } | ConvertTo-Json -Compress
    Invoke-EspoApi -Method PUT -Path 'Settings' -Body $threeApprovers -ExpectedStatus 400 | Out-Null

    $deactivate = @{isActive = $false} | ConvertTo-Json -Compress
    Invoke-EspoApi -Method PUT -Path "User/$($userIds[2])" -Body $deactivate | Out-Null
    Invoke-EspoApi -Method PUT -Path 'Settings' -Body $threeApprovers -ExpectedStatus 400 | Out-Null

    $selectedIds = @($userIds[0], $userIds[1])
    $selectedNames = @{}
    $selectedNames[$userIds[0]] = $userNames[$userIds[0]]
    $selectedNames[$userIds[1]] = $userNames[$userIds[1]]
    $twoApprovers = @{
        holidayManagementApproversIds = $selectedIds
        holidayManagementApproversNames = $selectedNames
    } | ConvertTo-Json -Compress
    Invoke-EspoApi -Method PUT -Path 'Settings' -Body $twoApprovers | Out-Null

    $settings = Invoke-EspoApi -Method GET -Path 'Settings'
    Assert-Equal $settings.holidayManagementApproversIds.Count 2 'Accepted approvers did not persist.'
    if (($settings.holidayManagementApproversIds -notcontains $selectedIds[0]) -or
        ($settings.holidayManagementApproversIds -notcontains $selectedIds[1])) {
        throw 'The persisted approver selection does not contain both selected users.'
    }

    Write-Output 'PHASE-001 Docker install/settings tests passed.'
}
finally {
    if (Test-Path $responseFile) {
        Remove-Item -LiteralPath $responseFile -Force
    }

    if (-not $KeepEnvironment) {
        docker compose -p $projectName -f $composeFile down -v --remove-orphans
    }
}
