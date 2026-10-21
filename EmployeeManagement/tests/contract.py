"""Read-only package/source contract checks; no EspoCRM or DB writes."""
import hashlib
import json
from pathlib import Path
import re
import zipfile

root = Path(__file__).resolve().parents[1]
repo = root.parent
manifest = json.loads((root / 'manifest.json').read_text())
package = repo / ('dist/employee-management-' + manifest['version'] + '.zip')
assert manifest['acceptableVersions'] == ['10.0.1']
assert manifest['php'] == ['8.4.23']
assert manifest['dependencies'] == {'Zile Sărbătoare': ['0.9.5']}
modules = list((root / 'files/custom/Espo/Modules').iterdir())
assert [p.name for p in modules] == ['EmployeeManagement']

def mechanical(text):
    text = text.replace('AttendanceManagement', 'EmployeeManagement').replace('HolidayManagement', 'EmployeeManagement')
    text = text.replace('attendance-management', 'employee-management').replace('holiday-management', 'employee-management')
    text = text.replace('attendanceManagement', 'employeeAttendance').replace('holidayManagement', 'employeeHoliday')
    for entity in ['AttendanceRecord', 'AttendanceWorkSchedule', 'HolidayRequest', 'HolidayProfile', 'HolidayLedger']:
        text = re.sub(r'\b' + entity + r'\b', 'Employee' + entity, text)
    return text

reuse = json.loads((root / 'REUSE-MAP.json').read_text())
stage4=json.loads((root/'STAGE-4-REUSE.json').read_text())
adapted4={i['destination'] for i in stage4}
focused_release_adaptations={
    'files/custom/Espo/Modules/EmployeeManagement/Tools/HolidayDocument/HolidayDocumentGenerator.php',
    'files/custom/Espo/Modules/EmployeeManagement/Tools/HolidayDocument/HolidayApprovalDocumentService.php',
    'files/custom/Espo/Modules/EmployeeManagement/Tools/HolidayBalance/HolidayRequestService.php',
    'files/client/custom/modules/employee-management/src/views/holiday-request/index.js',
    'files/custom/Espo/Modules/EmployeeManagement/Resources/metadata/entityDefs/EmployeeHolidayRequest.json',
}
for item in reuse:
    source = repo / item['source']
    destination = root / item['destination']
    assert hashlib.sha256(source.read_bytes()).hexdigest() == item['sha256'], item['source']
    assert destination.is_file(), item['destination']
    if destination.suffix == '.php':
        if item['destination'] not in adapted4 | focused_release_adaptations:
            assert destination.read_text() == mechanical(source.read_text()), item['destination']
print(f'PASS: {len(reuse)} original imports preserved; only provenance-listed or focused-release destinations may contain targeted adaptations.')

template = 'files/custom/Espo/Modules/EmployeeManagement/Resources/templates/holiday-request.docx'
assert (root / template).read_bytes() == (repo / 'HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Resources/templates/holiday-request.docx').read_bytes()
metadata = modules[0] / 'Resources/metadata'
assert not (metadata / 'clientDefs/Calendar.json').exists()
assert not (metadata / 'entityDefs/Settings.json').exists()
scheduled=json.loads((metadata / 'app/scheduledJobs.json').read_text())
assert set(scheduled)=={'ProcessEmployeeHolidayResets'}
assert scheduled['ProcessEmployeeHolidayResets']['scheduling']=='5 0 * * *'
assert not (metadata / 'app/adminPanel.json').exists()
routes = json.loads((metadata / 'app/clientRoutes.json').read_text())
assert set(routes) == {'EmployeeManagement', 'EmployeeAdministration', 'EmployeeAdministration/:section', 'EmployeeManagement/holiday', 'EmployeeHolidayDocumentVerification', 'EmployeeAttendance', 'EmployeeAttendanceOverview', 'EmployeeAttendance/month/:month', 'EmployeeHolidayRequest', 'EmployeeHolidayRequest/create'}
acl = json.loads((metadata / 'app/acl.json').read_text())
assert 'mandatory' not in acl
assert all(value is False for value in acl['strictDefault']['scopeLevel'].values())
for path in metadata.rglob('*.json'):
    json.loads(path.read_text())
for entity in ['EmployeeAttendanceRecord', 'EmployeeHolidayRequest', 'EmployeeHolidayProfile', 'EmployeeHolidayLedger']:
    defs = json.loads((metadata / f'entityDefs/{entity}.json').read_text())
    assert 'assignedUser' in defs['fields'] and 'user' not in defs['fields']
    assert defs['links']['assignedUser']['entity'] == 'User'
    record = json.loads((metadata / f'recordDefs/{entity}.json').read_text())
    assert record['exportDisabled'] is True

with zipfile.ZipFile(package) as archive:
    assert all(info.filename.startswith(('files/', 'scripts/')) for info in archive.infolist() if info.is_dir())
    names = [info.filename for info in archive.infolist() if not info.is_dir()]
    assert 'manifest.json' in names and template in names
    assert not any(n.startswith(('pending/', 'tests/', 'docs/')) for n in names)
    assert all(n == 'manifest.json' or n == 'README.md' or n.startswith('files/') or n in ['scripts/AfterInstall.php','scripts/AfterUninstall.php','scripts/reissue-holiday-document.php'] for n in names)
    assert not any('AttendanceManagement/' in n or 'HolidayManagement/' in n for n in names)
    for name in names:
        if name.endswith(('.php', '.json', '.js')):
            text = archive.read(name).decode()
            assert 'Espo\\Modules\\AttendanceManagement' not in text
            assert 'Espo\\Modules\\HolidayManagement' not in text
print('PASS: package allowlist, native ACL defaults, ownership, no global Admin/Calendar overrides.')

stage = json.loads((root / 'STAGE-2-REUSE.json').read_text())
for item in stage:
    assert hashlib.sha256((root / item['source']).read_bytes()).hexdigest() == item['sourceSha256'], item['source']
    assert (root / item['destination']).is_file(), item['destination']
inventory = json.loads((root / 'MIGRATION-INVENTORY.json').read_text())
runtime_sources = {p.relative_to(repo).as_posix() for extension in ['AttendanceManagement', 'HolidayManagement']
    for directory in ['files', 'scripts'] for p in (repo / extension / directory).rglob('*') if p.is_file()}
assert runtime_sources == {i['original'] for i in inventory['originalRuntimeAndLifecycleFiles']}
for item in inventory['originalRuntimeAndLifecycleFiles']:
    assert hashlib.sha256((repo / item['original']).read_bytes()).hexdigest() == item['originalSha256']
    assert item['reason']
    assert all((root / path).is_file() for path in item['preservedAt'])
for item in inventory['integratedStage2']:
    assert hashlib.sha256((root / item['destination']).read_bytes()).hexdigest() == item['destinationSha256']
print(f'PASS: stage 2 provenance and complete inventory of {len(runtime_sources)} original runtime/lifecycle files.')

stage3=json.loads((root/'STAGE-3-REUSE.json').read_text())
for item in stage3:
    assert hashlib.sha256((root/item['source']).read_bytes()).hexdigest()==item['sourceSha256'],item['source']
for item in inventory['integratedStage3']:
    if item['destination'] not in focused_release_adaptations:
        assert hashlib.sha256((root/item['destination']).read_bytes()).hexdigest()==item['destinationSha256'],item['destination']
assert manifest['version']=='0.8.14'
status = (modules[0]/'Tools/Workspace/Api/GetStatus.php').read_text()
assert "'version' => '" + manifest['version'] + "'" in status

# 0.8.14 keeps Holiday Ledger on the native Record stack and makes the
# presentation contract explicit in package validation.
ledger_entity=json.loads((metadata/'entityDefs/EmployeeHolidayLedger.json').read_text())
ledger_list=json.loads((modules[0]/'Resources/layouts/EmployeeHolidayLedger/list.json').read_text())
ledger_list_small=json.loads((modules[0]/'Resources/layouts/EmployeeHolidayLedger/listSmall.json').read_text())
ledger_detail=json.loads((modules[0]/'Resources/layouts/EmployeeHolidayLedger/detail.json').read_text())
ledger_filters=json.loads((modules[0]/'Resources/layouts/EmployeeHolidayLedger/filters.json').read_text())
ledger_client=json.loads((metadata/'clientDefs/EmployeeHolidayLedger.json').read_text())
ledger_scope=json.loads((metadata/'scopes/EmployeeHolidayLedger.json').read_text())
ledger_entity_acl=json.loads((metadata/'entityAcl/EmployeeHolidayLedger.json').read_text())
assert ledger_entity['collection']=={'orderBy': 'createdAt', 'order': 'desc'}
assert [item['name'] for item in ledger_list]==[
    'createdAt', 'type', 'assignedUser', 'balanceBefore', 'delta', 'balanceAfter', 'actor'
]
assert [item['name'] for item in ledger_list_small]==['createdAt', 'type', 'delta', 'balanceAfter']
assert ledger_filters==['assignedUser', 'type', 'createdAt', 'effectiveDate', 'actor']
assert ledger_client['controller']=='controllers/record'
assert ledger_client['iconClass']=='fas fa-history'
assert ledger_client['views']['list']=='employee-management:views/holiday-ledger/list'
assert ledger_client['recordViews']['list']=='employee-management:views/holiday-ledger/record/list'
assert all(ledger_client[key] is True for key in [
    'createDisabled', 'editDisabled', 'removeDisabled', 'massUpdateDisabled', 'massRemoveDisabled'
])
assert ledger_scope['aclActionLevelListMap']['read']==['all', 'own', 'no']
assert all(ledger_scope['aclActionLevelListMap'][action]==['no'] for action in ['create', 'edit', 'delete'])
ledger_detail_fields={
    field['name']
    for panel in ledger_detail
    for row in panel['rows']
    for field in row if field
}
assert {'type', 'assignedUser', 'createdAt', 'balanceBefore', 'delta', 'balanceAfter'} <= ledger_detail_fields
assert not {'idempotencyKey', 'operationHash'} & ledger_detail_fields
assert ledger_entity_acl['fields']['idempotencyKey']['forbidden'] is True
assert ledger_entity_acl['fields']['operationHash']['forbidden'] is True
assert not (modules[0]/'Tools/HolidayBalance/Api/EmployeeHolidayLedger.php').exists()
print('PASS: native Holiday Ledger list/detail/filter/ordering, Own/All ACL, and read-only package contract.')

assert not (modules[0]/'Hooks/EmployeeHolidayRequest/Balance.php').exists(), 'Commands exclusively own request accounting.'
service=(modules[0]/'Tools/HolidayBalance/HolidayRequestService.php').read_text()
assert '$this->approvalDocumentService->generate' not in service
assert "'status' => ['Pending', 'ApprovalProcessing', 'Approved', 'CancellationPending']" in service
print(f'PASS: stage 3 reuse provenance ({len(stage3)} components), exclusive accounting owner, no synchronous approval fallback.')
for item in stage4:
    assert hashlib.sha256((root/item['source']).read_bytes()).hexdigest()==item['sourceSha256'],item['source']
for item in inventory['integratedStage4']:
    if item['destination'] not in focused_release_adaptations:
        assert hashlib.sha256((root/item['destination']).read_bytes()).hexdigest()==item['destinationSha256'],item['destination']
schedule=json.loads((metadata/'entityDefs/EmployeeAttendanceWorkSchedule.json').read_text())
assert 'startTime' not in schedule['fields'] and 'endTime' not in schedule['fields']
assert 'assignedUser' in schedule['fields'] and 'user' not in schedule['fields']
assert json.loads((metadata/'entityDefs/EmployeeAttendanceMonth.json').read_text())['indexes']['monthUnique']['unique']
assert not (modules[0]/'Controllers/EmployeeAttendanceWorkSchedule.php').exists()
print(f'PASS: Stage 4 provenance ({len(stage4)} integrations), no parallel norm editor, monthly native ORM state, no generic metadata mutation controller.')

stage5=json.loads((root/'STAGE-5-REUSE.json').read_text())
for item in stage5:
    assert hashlib.sha256((root/item['source']).read_bytes()).hexdigest()==item['sourceSha256'], item['source']
for item in inventory['integratedStage5']:
    if item['destination'] not in focused_release_adaptations:
        assert hashlib.sha256((root/item['destination']).read_bytes()).hexdigest()==item['destinationSha256'], item['destination']
assert inventory['stage']==manifest['version']
assert service.count('$this->attendanceGuard->assertCompatible($request);')==3
assert '$this->months->snapshot($user, $start, $end)' in service
assert '$this->approvals->initiate(' in service and '$this->approvals->retry($request)' in service
guard=(modules[0]/'Tools/Attendance/HolidayAttendanceGuard.php').read_text()
assert "'status' => ['AtWork', 'BusinessTrip']" in guard
assert 'saveEntity' not in guard and 'set(' not in guard
for component in inventory['newIntegrationComponentsStage5']:
    assert (root/component['file']).is_file() and component['reason']
with zipfile.ZipFile(package) as archive:
    for name in [info.filename for info in archive.infolist() if not info.is_dir()]:
        assert archive.read(name)==(root/name).read_bytes(), name
print(f'PASS: Stage 5 provenance ({len(stage5)} adaptations), shared immutable norms, three conflict boundaries, no approval bypass; ZIP exactly matches current sources.')

stage6=json.loads((root/'STAGE-6-REUSE.json').read_text())
for item in stage6:
    assert hashlib.sha256((root/item['source']).read_bytes()).hexdigest()==item['sourceSha256'],item['source']
for item in inventory['integratedStage6']:
    if item['destination'] not in focused_release_adaptations:
        assert hashlib.sha256((root/item['destination']).read_bytes()).hexdigest()==item['destinationSha256'],item['destination']
for item in inventory['newIntegrationComponentsStage6']:
    assert (root/item['file']).is_file() and item['reason']
approval=(modules[0]/'Tools/HolidayDocument/HolidayApprovalService.php').read_text()
assert 'JobSchedulerFactory' in approval and '->setGroup($group)' in approval
assert "['Pending','Ready','Running']" in approval and '->setTargetField(\'approvalStaging\')' in approval
assert "'status'=>'Approved'" in approval and '$this->attendance->assertCompatible($request)' in approval
assert 'createLedger' not in approval and "->set('balance'" not in approval
restricted=json.loads((metadata/'entityAcl/EmployeeHolidayRequest.json').read_text())['fields']
assert restricted['approvalStaging']['forbidden'] and restricted['approvalSnapshot']['forbidden']
assert restricted['approvalToken']['forbidden'] and restricted['approvalManifest']['forbidden']
assert not (metadata/'aclDefs/Attachment.json').exists(), 'Preserve native Attachment parent/field ACL.'
print(f'PASS: Stage 6 provenance ({len(stage6)} reused components), native grouped Jobs, staged native ACL, unchanged accounting owner and native Attachment checker.')

stage7=json.loads((root/'STAGE-7-REUSE.json').read_text())
for item in stage7:
    assert hashlib.sha256((root/item['source']).read_bytes()).hexdigest()==item['sourceSha256'],item['source']
for item in inventory['integratedStage7']:
    if item['destination'] not in focused_release_adaptations:
        assert hashlib.sha256((root/item['destination']).read_bytes()).hexdigest()==item['destinationSha256'],item['destination']
for item in inventory['newIntegrationComponentsStage7']:
    assert (root/item['file']).is_file() and item['reason']
for item in inventory['newIntegrationComponentsVersion0812']:
    assert (root/item['file']).is_file() and item['reason']
absence=json.loads((metadata/'entityDefs/EmployeeAbsence.json').read_text())
assert set(absence['fields'])=={'name','dateStart','dateEnd','assignedUser','sourceId','createdAt','color'}
assert absence['fields']['color']['notStorable'] is True
assert set(absence['links'])=={'assignedUser'} and absence['indexes']['sourceUnique']['unique']
assert json.loads((metadata/'entityAcl/EmployeeAbsence.json').read_text())['fields']['sourceId']['forbidden']
assert acl['strictDefault']['scopeLevel']['EmployeeAbsence'] is False
assert (modules[0]/'Hooks/EmployeeHolidayRequest/Absence.php').exists()
assert not (modules[0]/'Hooks/EmployeeHolidayRequest/Balance.php').exists()
profile=(modules[0]/'Tools/HolidayBalance/HolidayProfileService.php').read_text()
assert 'BalanceMath::calculateResetBalance(' in profile and 'scheduled-reset:%s:%s' in profile
assert 'private function profileOwnerId(' in profile and 'if (!$this->user->isSystem())' in profile
assert "get('employeeHolidayAutomaticAnnualGrantEnabled') !== true" in profile
assert "'status' => 'Pending'" in (modules[0]/'Services/EmployeeHolidayRequest.php').read_text()
assert 'calendarView' not in (metadata/'clientDefs/EmployeeAbsence.json').read_text()
assert (root/'scripts/AfterUninstall.php').exists() and not (root/'scripts/BeforeUninstall.php').exists()
print(f'PASS: Stage 7 provenance ({len(stage7)} adaptations), minimal native Calendar projection, opt-in annual ScheduledJob, own-only lifecycle and strict read-only ACL.')

# Regression: operational navigation must never send a delegated manager into native Admin.
for path in (root/'files/client/custom/modules/employee-management').rglob('*'):
    if path.is_file() and path.suffix in {'.js','.tpl'}:
        assert '#Admin' not in path.read_text(), path
for scope in ['EmployeeManagement','EmployeeAdministration','EmployeeAttendance','EmployeeAttendanceOverview']:
    assert json.loads((metadata/f'scopes/{scope}.json').read_text())['tab'] is True
    for locale in ['en_US','ro_RO']:
        assert json.loads((modules[0]/f'Resources/i18n/{locale}/Global.json').read_text())['scopeNamesPlural'][scope]
overview=(root/'files/client/custom/modules/employee-management/src/views/attendance/overview.js').read_text()
assert '#EmployeeAdministration/attendanceMetadata' in overview
assert '{{#if canManageSchedules}}' in overview
for token in ["shortLabel: 'P'", "shortLabel: 'D'", "shortLabel: 'CM'",
              "attendance-manager-status-button", "managerMarkRoutine", "aria-pressed"]:
    assert token in overview
assert 'attendance-correction' not in overview
assert "reason, revision" not in overview
attendance_api=(modules[0]/'Tools/Attendance/Api/Attendance.php').read_text()
attendance_overview=(modules[0]/'Tools/Attendance/AttendanceOverviewService.php').read_text()
assert "'managerMarkRoutine'=>['userId','date','status','revision']" in attendance_api
assert 'markForUserRoutine' in attendance_overview
assert "!$routineManagerMark && trim((string)$reason)===''" in attendance_overview
assert "($state->get('frozen') || $month<$this->getEditableFromMonth($today))" in attendance_overview
after_install=(root/'scripts/AfterInstall.php').read_text()
for forbidden in ['AttendanceDataResetService', 'HolidayDataResetService', 'testDataReset', 'resetAttendance']:
    assert forbidden not in after_install
tab_list=(modules[0]/'Tools/Navigation/TabList.php').read_text()
assert 'tabList' not in after_install and 'TabList::' not in after_install
assert 'public static function remove' in tab_list and 'array_splice' not in tab_list
print('PASS: install/upgrade does not mutate sidenav; scopes remain manually available; delegated Schedules link uses server authorization.')

# Functional fixes: creation URLs use the guarded booking adapter, not generic CRUD.
assert routes['EmployeeHolidayRequest']['params']=={'controller':'employee-management:controllers/workspace','action':'holiday'}
assert routes['EmployeeHolidayRequest/create']['params']=={'controller':'employee-management:controllers/workspace','action':'createHoliday'}
holiday_client=json.loads((metadata/'clientDefs/EmployeeHolidayRequest.json').read_text())
assert holiday_client['controller']=='controllers/record'
assert holiday_client['iconClass']=='fas fa-umbrella-beach'
assert holiday_client['createDisabled'] is True
holiday_page=(root/'files/client/custom/modules/employee-management/src/views/holiday-request/index.js').read_text()
for key in ['balance.availableDays','balance.pendingDays','balance.annualEntitlement','balance.nextResetDate']:
    assert key in holiday_page
for token in ['employee-holiday-balance-grid', 'employee-holiday-request-list',
              'data-action="create"', 'statusClass(row.status)', 'hasDocuments:documents.length > 0']:
    assert token in holiday_page
for token in ['data-action="approved"', "this.switchListMode('approved')", "const endpoint = mode === 'own' ? 'requests' : mode",
              "row.dateStartDate >= this.getDateTime().getToday()"]:
    assert token in holiday_page
for token in ['pollForUpdates()', 'makeRequestSignature(response.list || [])', 'getPollingDelay()',
              'document.addEventListener', 'document.removeEventListener', 'recordPollingFailure()',
              "row.status === 'Approved'", "row.status === 'Rejected'"]:
    assert token in holiday_page
assert 'setInterval' not in holiday_page
assert "this.listMode !== 'own' && !this.rows.some" not in holiday_page
assert 'baseDelay = this.rows.some(row => row.processing) ? 5000 : 30000' in holiday_page
assert 'Math.min(120000' in holiday_page
assert 'buildApprovalDocumentMap($requests)' in service
assert "->select(self::LIST_FIELDS)" in service
assert (root/'tests/regression/holiday-polling.test.mjs').is_file()
assert 'listApprovedRequests()' in service
holiday_api=(modules[0]/'Tools/HolidayBalance/Api/Holiday.php').read_text()
assert "'approved' => $this->service->listApprovedRequests()" in holiday_api
assert "'calculate' => ['dateStart', 'dateEnd', 'userId', 'managed', 'requestId']" in holiday_api
assert 'calculatePreview($values)' in holiday_api
assert 'public function calculatePreview(array $data): array' in service
booking_modal=(root/'files/client/custom/modules/employee-management/src/views/modals/managed-booking.js').read_text()
for token in ['employee-holiday-live-calculation', 'scheduleCalculation()',
              'EmployeeManagement/holiday/calculate', "this.formModel.set('days'"]:
    assert token in booking_modal
decision_notifier=(modules[0]/'Tools/HolidayBalance/HolidayDecisionNotifier.php').read_text()
for token in ['Notification::TYPE_MESSAGE', "'employeeHolidayType' => 'decision'",
              'holidayApprovedEmployeeNotification', 'holidayRejectedEmployeeNotification']:
    assert token in decision_notifier
assert "$this->decisionNotifier->notify($request, 'Rejected')" in service
assert "$this->decisionNotifier->notify($request, 'Approved')" in approval
submission_notifier=(modules[0]/'Tools/HolidayBalance/HolidaySubmissionNotifier.php').read_text()
submission_job=(modules[0]/'Jobs/SendHolidayApprovalNotification.php').read_text()
approver_provider=(modules[0]/'Tools/HolidayAdministration/HolidayApproverProvider.php').read_text()
for token in ['EmailSender', 'JobSchedulerFactory', 'Email::STATUS_DRAFT', 'Email::STATUS_SENDING',
              'Email::STATUS_SENT', 'Email::STATUS_FAILED', "private const MAX_ATTEMPTS = 3",
              "2 => 'PT1M'", "3 => 'PT5M'", "self::EVENT, $requestId, $recipientId",
              "get('siteUrl')", '/#EmployeeHolidayRequest']:
    assert token in submission_notifier
assert '$this->submissionNotifier->queue($request)' in service
assert '$this->notifier->deliver(' in submission_job
assert 'canReadApprovalRequest' in approver_provider
assert "getLevel(self::REQUEST, 'read') === 'all'" in approver_provider
assert 'getScopeForbiddenAttributeList' in approver_provider
assert 'SendHolidayApprovalNotification' not in after_install
assert "'assignedUserId' => $recipient->getId()" not in submission_notifier
assert "get('dateStartDate') < $this->dateTime->getToday()->toString()" in service
client_css=json.loads((metadata/'app/client.json').read_text())['cssList']
assert 'client/custom/modules/employee-management/css/holiday.css' in client_css
holiday_css=(root/'files/client/custom/modules/employee-management/css/holiday.css').read_text()
for token in ['--tuvtk-primary', '--tuvtk-secondary', '--tuvtk-surface',
              '.employee-holiday-balance-grid', '.employee-holiday-request-approved',
              '.employee-holiday-live-calculation', '@media']:
    assert token in holiday_css
attendance_page=(root/'files/client/custom/modules/employee-management/src/views/attendance/my.js').read_text()
for token in ['employee-attendance-hero', 'attendance-action-card', 'attendance-progress-copy',
              'attendance-status-pill', 'statusPresentation(status)', "status === 'MedicalLeave' ? day.canMarkMedical : day.canMark"]:
    assert token in attendance_page
attendance_ro=json.loads((modules[0]/'Resources/i18n/ro_RO/EmployeeAttendanceRecord.json').read_text())
assert attendance_ro['options']['status']['AtWork']=='Prezent'
attendance_pdf=(modules[0]/'Tools/Attendance/AttendancePdfGenerator.php').read_text()
attendance_xlsx=(modules[0]/'Tools/Attendance/AttendanceXlsxGenerator.php').read_text()
assert "'AtWork' => 'Prezent'" in attendance_pdf
assert "'AtWork' => 'Prezent'" in attendance_xlsx
attendance_overview=(root/'files/client/custom/modules/employee-management/src/views/attendance/overview.js').read_text()
for token in ['employee-attendance-overview-hero', 'attendance-overview-command-bar',
              'attendance-overview-matrix-count', 'attendance-summary-details',
              "translateOption 'ChildCareLeave'", 'renderMonthState(data)', 'matrix-empty',
              'EmployeeManagement/attendance/managerMark', 'EmployeeManagement/attendance/remind']:
    assert token in attendance_overview
attendance_metadata=(root/'files/client/custom/modules/employee-management/src/views/attendance/schedules.js').read_text()
for token in ['employee-attendance-metadata-page', 'attendance-schedule-configuration',
              'attendance-schedule-dirty-indicator', 'data-role="schedule-empty"',
              "'views/fields/date'", 'EmployeeManagement/attendance/schedule']:
    assert token in attendance_metadata
attendance_css=(root/'files/client/custom/modules/employee-management/css/attendance.css').read_text()
for token in ['--tuvtk-primary', '--tuvtk-secondary', '.employee-attendance-page',
              '.attendance-status-work', '.attendance-day-row-medical',
              '.employee-attendance-overview-page', '.attendance-overview-legend',
              'td.attendance-cell-AtWork', 'td.attendance-cell-ChildCareLeave',
              '.employee-attendance-metadata-page', '.attendance-schedule-choice', '@media']:
    assert token in attendance_css
workspace_controller=(root/'files/client/custom/modules/employee-management/src/controllers/workspace.js').read_text()
assert "views/holiday-request/index" in workspace_controller and "never generic Record.create" in workspace_controller
record_controller=(modules[0]/'Controllers/EmployeeHolidayRequest.php').read_text()
assert 'function actionCreate(' in record_controller and 'HolidayRequestService::class' in record_controller
assert json.loads((metadata/'app/clientNavbar.json').read_text()) == {}
assert 'employeeHolidayApproversIds' not in json.loads((metadata/'app/employeeManagement.json').read_text())['settings']['holiday']
for folder in ['Tools/HolidayBalance','Tools/HolidayDocument','Tools/HolidayAdministration']:
    for path in (modules[0]/folder).glob('*.php'):
        assert 'employeeHolidayApproversIds' not in path.read_text(), path
print('PASS: all Holiday create entries delegate to the accounting command; generic update/delete guards retained; redundant flyout entries removed.')

# Original Holiday test-data reset, adapted to delegated native ACL and unified artifacts.
reset_service=(modules[0]/'Tools/HolidayBalance/HolidayDataResetService.php').read_text()
for token in ['EmployeeHolidayProfile', 'EmployeeHolidayLedger', 'EmployeeHolidayRequest',
              'EmployeeAbsence', 'approvalStaging', 'GenerateHolidayApproval::class']:
    assert token in reset_service
assert "['Pending', 'Ready', 'Running']" in reset_service
assert 'employeeHolidayAutomaticAnnualGrantEnabled' in reset_service
assert "['balanceWarning', 'decision']" in reset_service
reset_view=(root/'files/client/custom/modules/employee-management/src/views/fields/test-data-reset.js').read_text()
assert 'RESET HOLIDAY DATA' in reset_view
assert 'administration/holiday/testDataResetPreview' in reset_view
assert 'administration/holiday/testDataReset' in reset_view
admin_api=(modules[0]/'Tools/Administration/Api/Administration.php').read_text()
assert 'assertHolidayDataReset' in admin_api and 'HolidayDataResetService' in admin_api
print('PASS: destructive Holiday test reset is explicit, previewed, ACL-gated, and cleans unified artifacts.')

# Attendance test-data reset preserves configuration and shared immutable norm snapshots.
attendance_reset=(modules[0]/'Tools/Attendance/AttendanceDataResetService.php').read_text()
for token in ['EmployeeAttendanceRecord', 'EmployeeAttendanceMonth',
              'EmployeeAttendanceWorkSchedule', 'employeeAttendanceArchiveStartMonth',
              'employeeManagementAttendanceReminder']:
    assert token in attendance_reset
assert "'frozen' => false" in attendance_reset
assert "'policies' => null" in attendance_reset
assert "'revision' => (int) $monthState->get('revision') + 1" in attendance_reset
assert "'norms' =>" not in attendance_reset
assert 'removeEntity($record' in attendance_reset
assert 'removeEntity($notification' in attendance_reset
attendance_reset_view=(root/'files/client/custom/modules/employee-management/src/views/fields/attendance-test-data-reset.js').read_text()
assert 'RESET ATTENDANCE DATA' in attendance_reset_view
assert 'administration/attendance/testDataResetPreview' in attendance_reset_view
assert 'administration/attendance/testDataReset' in attendance_reset_view
assert 'assertAttendanceDataReset' in admin_api and 'AttendanceDataResetService' in admin_api
assert 'attendance-today-action-column' in attendance_page
assert "translate 'Choose Attendance'" in attendance_page
print('PASS: destructive Attendance test reset is previewed and ACL-gated; WTC norms and configuration remain intact; personal action heading shares the button column.')
