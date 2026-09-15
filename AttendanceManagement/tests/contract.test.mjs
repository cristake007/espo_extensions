import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';

const extensionRoot = path.resolve(import.meta.dirname, '..');
const moduleRoot = path.join(
    extensionRoot,
    'files', 'custom', 'Espo', 'Modules', 'AttendanceManagement'
);
const clientRoot = path.join(
    extensionRoot,
    'files', 'client', 'custom', 'modules', 'attendance-management'
);

async function readJson(...segments) {
    return JSON.parse(await readFile(path.join(moduleRoot, ...segments), 'utf8'));
}

async function readSource(...segments) {
    return readFile(path.join(moduleRoot, ...segments), 'utf8');
}

test('manifest packages a standalone EspoCRM 10 attendance module', async () => {
    const manifest = JSON.parse(await readFile(
        path.join(extensionRoot, 'manifest.json'),
        'utf8'
    ));
    const module = await readJson('Resources', 'module.json');

    assert.equal(manifest.name, 'Attendance Management');
    assert.equal(manifest.version, '1.4.1');
    assert.deepEqual(manifest.acceptableVersions, ['>=10.0.0']);
    assert.equal(module.jsTranspiled, false);
});

test('attendance records are private service-managed daily records', async () => {
    const defs = await readJson('Resources', 'metadata', 'entityDefs', 'AttendanceRecord.json');
    const scope = await readJson('Resources', 'metadata', 'scopes', 'AttendanceRecord.json');
    const acl = await readJson('Resources', 'metadata', 'aclDefs', 'AttendanceRecord.json');
    const recordDefs = await readJson('Resources', 'metadata', 'recordDefs', 'AttendanceRecord.json');

    assert.deepEqual(defs.fields.status.options, ['AtWork', 'Holiday', 'BusinessTrip']);
    assert.deepEqual(defs.indexes.userDateUnique.columns, ['userId', 'date']);
    assert.equal(defs.indexes.userDateUnique.unique, true);
    assert.equal(scope.module, 'AttendanceManagement');
    assert.equal(scope.tab, false);
    assert.equal(scope.customizable, false);
    assert.equal(acl.read, false);
    assert.match(recordDefs.beforeCreateHookClassNameList[0], /AttendanceManagement/);
    assert.match(recordDefs.beforeUpdateHookClassNameList[0], /AttendanceManagement/);
    assert.match(recordDefs.beforeDeleteHookClassNameList[0], /AttendanceManagement/);
});

test('personal API is self-service only and rejects future or non-working dates', async () => {
    const routes = await readJson('Resources', 'routes.json');
    const service = await readSource('Tools', 'Attendance', 'AttendanceService.php');

    assert.deepEqual(routes.slice(0, 2).map(item => [item.route, item.method]), [
        ['/AttendanceManagement/myAttendance', 'get'],
        ['/AttendanceManagement/mark', 'post'],
    ]);
    assert.match(service, /'userId' => \$this->user->getId\(\)/);
    assert.match(service, /if \(\$date > \$today\)/);
    assert.match(service, /Attendance cannot be marked for a future date/);
    assert.match(service, /STATUS_AT_WORK, self::STATUS_BUSINESS_TRIP/);
    assert.match(service, /\(int\) \$date->format\('N'\) <= 5/);
    assert.match(service, /nonWorkingDayProvider->getDates/);
    assert.match(service, /'todayCanMark' => \$todayState\['canMark'\]/);
});

test('manager API is protected and available to configured managers or holiday approvers', async () => {
    const routes = await readJson('Resources', 'routes.json');
    const checker = await readSource('Tools', 'Attendance', 'AttendanceAccessChecker.php');
    const settings = await readJson('Resources', 'metadata', 'entityDefs', 'Settings.json');
    const validator = await readSource('FieldValidators', 'Settings', 'Managers', 'Valid.php');

    assert.deepEqual(routes.slice(2).map(item => [item.route, item.method]), [
        ['/AttendanceManagement/overview/access', 'get'],
        ['/AttendanceManagement/overview', 'get'],
        ['/AttendanceManagement/overview/remind', 'post'],
        ['/AttendanceManagement/schedules', 'get'],
        ['/AttendanceManagement/overview/schedule', 'post'],
        ['/AttendanceManagement/overview/xlsx', 'post'],
    ]);
    assert.equal(settings.fields.attendanceManagementManagers.type, 'linkMultiple');
    assert.equal(settings.fields.attendanceManagementManagers.entity, 'User');
    assert.match(checker, /attendanceManagementManagersIds/);
    assert.match(checker, /holidayManagementApproversIds/);
    assert.match(checker, /assertManager/);
    assert.match(validator, /User::TYPE_REGULAR, User::TYPE_ADMIN/);
    assert.match(validator, /'isActive' => true/);
});

test('manager overview reports signed, missing and future cells and overlays holidays', async () => {
    const service = await readSource('Tools', 'Attendance', 'AttendanceOverviewService.php');

    assert.match(service, /accessChecker->assertManager/);
    assert.match(service, /approvedHolidayProvider\s*->getDates/);
    assert.match(service, /'isMissing' => \$isMissing/);
    assert.match(service, /'isFuture' => \$isFuture/);
    assert.match(service, /'missingUsers' => \$missingUsers/);
    assert.match(service, /\$signedCount > 0/);
    assert.match(service, /\$missingCount === 0/);
    assert.match(service, /\$scheduleMissingCount === 0/);
    assert.doesNotMatch(service, /\$futureCount === 0,/);
});

test('working schedules are persistent defaults edited from administration', async () => {
    const routes = await readJson('Resources', 'routes.json');
    const defs = await readJson(
        'Resources', 'metadata', 'entityDefs', 'AttendanceWorkSchedule.json'
    );
    const scope = await readJson(
        'Resources', 'metadata', 'scopes', 'AttendanceWorkSchedule.json'
    );
    const acl = await readJson(
        'Resources', 'metadata', 'aclDefs', 'AttendanceWorkSchedule.json'
    );
    const service = await readSource('Tools', 'Attendance', 'AttendanceOverviewService.php');
    const checker = await readSource('Tools', 'Attendance', 'AttendanceAccessChecker.php');
    const settings = await readJson('Resources', 'metadata', 'entityDefs', 'Settings.json');
    const settingsView = await readFile(
        path.join(clientRoot, 'src', 'views', 'fields', 'work-schedules.js'),
        'utf8'
    );

    assert.ok(routes.some(item =>
        item.route === '/AttendanceManagement/schedules' && item.method === 'get'
    ));
    assert.ok(routes.some(item =>
        item.route === '/AttendanceManagement/overview/schedule' && item.method === 'post'
    ));
    assert.equal(defs.fields.startTime.maxLength, 5);
    assert.equal(defs.fields.endTime.maxLength, 5);
    assert.equal(defs.fields.effectiveFrom.type, 'date');
    assert.equal(defs.indexes.userEffectiveFromUnique.unique, true);
    assert.deepEqual(defs.indexes.userEffectiveFromUnique.columns, ['userId', 'effectiveFrom']);
    assert.equal(scope.tab, false);
    assert.equal(scope.customizable, false);
    assert.equal(acl.read, false);
    assert.match(service, /accessChecker->assertScheduleEditor/);
    assert.match(checker, /user->isAdmin\(\)/);
    assert.doesNotMatch(service, /effectiveFrom<=/);
    assert.match(service, /order\('effectiveFrom', 'DESC'\)/);
    assert.match(service, /Working schedule times must use the HH:MM format/);
    assert.match(service, /end time must be after the start time/);
    assert.equal(settings.fields.attendanceManagementScheduleEditor.notStorable, true);
    assert.match(settings.fields.attendanceManagementScheduleEditor.view, /work-schedules/);
    assert.match(settingsView, /AttendanceManagement\/schedules/);
    assert.match(settingsView, /AttendanceManagement\/overview\/schedule/);
    assert.doesNotMatch(settingsView, /month:/);
});

test('manager reminders create native EspoCRM notifications only for missing users', async () => {
    const service = await readSource('Tools', 'Attendance', 'AttendanceOverviewService.php');

    assert.match(service, /foreach \(\$overview\['missingUsers'\] as \$missingUser\)/);
    assert.match(service, /Notification::ENTITY_TYPE/);
    assert.match(service, /Notification::TYPE_MESSAGE/);
    assert.match(service, /'userId' => \$missingUser\['id'\]/);
    assert.match(service, /'url' => '#Attendance'/);
});

test('completed overview exports a landscape XLSX with schedule and no signature field', async () => {
    const generator = await readSource('Tools', 'Attendance', 'AttendanceXlsxGenerator.php');
    const action = await readSource('Tools', 'Attendance', 'Api', 'PostAttendanceXlsx.php');

    assert.match(generator, /PhpOffice\\PhpSpreadsheet\\Spreadsheet/);
    assert.match(generator, /ORIENTATION_LANDSCAPE/);
    assert.match(generator, /Condica de prezenta_%s %d\.xlsx/);
    assert.match(generator, /Ora intrare/);
    assert.match(generator, /Ora iesire/);
    assert.match(generator, /startTime/);
    assert.match(generator, /endTime/);
    assert.doesNotMatch(generator, /Semnatura|Semnătură|Signature/);
    assert.match(action, /if \(!\$overview\['downloadReady'\]\)/);
    assert.match(action, /base64_encode/);
});

test('approved holidays are read without changing Holiday Management', async () => {
    const provider = await readSource('Tools', 'Attendance', 'ApprovedHolidayProvider.php');
    const service = await readSource('Tools', 'Attendance', 'AttendanceService.php');

    assert.match(provider, /ENTITY_TYPE = 'HolidayRequest'/);
    assert.match(provider, /'status' => self::STATUS_APPROVED/);
    assert.match(provider, /'assignedUserId' => \$userId/);
    assert.match(provider, /'dateStartDate<=' => \$dateEnd/);
    assert.match(provider, /'dateEndDate>=' => \$dateStart/);
    assert.match(service, /SOURCE_APPROVED_HOLIDAY/);
    assert.match(service, /isApprovedHoliday/);
    assert.match(service, /An approved holiday controls attendance for this date/);
});

test('personal page is a side-navigation scope with two manual actions per working day', async () => {
    const controller = await readFile(
        path.join(clientRoot, 'src', 'controllers', 'attendance.js'),
        'utf8'
    );
    const view = await readFile(
        path.join(clientRoot, 'src', 'views', 'attendance', 'my.js'),
        'utf8'
    );
    const scope = await readJson('Resources', 'metadata', 'scopes', 'Attendance.json');
    const clientDefs = await readJson('Resources', 'metadata', 'clientDefs', 'Attendance.json');
    const afterInstall = await readFile(
        path.join(extensionRoot, 'scripts', 'AfterInstall.php'),
        'utf8'
    );

    assert.equal(scope.entity, false);
    assert.equal(scope.tab, true);
    assert.equal(clientDefs.controller, 'attendance-management:controllers/attendance');
    assert.match(controller, /actionIndex\(\)/);
    assert.match(afterInstall, /NAVIGATION_SCOPE_LIST = \['Attendance', 'AttendanceOverview'\]/);
    assert.match(afterInstall, /'type' => 'group'/);
    assert.match(afterInstall, /'id' => self::NAVIGATION_GROUP_ID/);
    assert.match(afterInstall, /'itemList' => self::NAVIGATION_SCOPE_LIST/);
    assert.match(view, /data-status="AtWork"/);
    assert.match(view, /data-status="BusinessTrip"/);
    assert.doesNotMatch(view, /data-status-indicator="Holiday"/);
    assert.match(view, /for \(const status of \['AtWork', 'BusinessTrip'\]\)/);
    assert.match(view, /prop\('disabled', !day.canMark\)/);
});

test('manager page has conditional side navigation, matrix, reminders and XLSX download', async () => {
    const overview = await readFile(
        path.join(clientRoot, 'src', 'views', 'attendance', 'overview.js'),
        'utf8'
    );
    const menu = await readFile(
        path.join(clientRoot, 'js', 'manager-menu.js'),
        'utf8'
    );
    const css = await readFile(path.join(clientRoot, 'css', 'attendance.css'), 'utf8');
    const scope = await readJson('Resources', 'metadata', 'scopes', 'AttendanceOverview.json');

    assert.equal(scope.tab, true);
    assert.match(menu, /AttendanceManagement\/overview\/access/);
    assert.match(menu, /attendance-management-manager/);
    assert.match(overview, /attendance-overview-table/);
    assert.match(overview, /send-reminders/);
    assert.match(overview, /download-xlsx/);
    assert.match(overview, /AttendanceManagement\/overview\/remind/);
    assert.match(overview, /AttendanceManagement\/overview\/xlsx/);
    assert.match(overview, /attendance-schedule-value/);
    assert.doesNotMatch(overview, /data-schedule-field/);
    assert.match(css, /#navbar a\[data-name="AttendanceOverview"\]/);
});

test('page is full-width and uses an EspoCRM date field for month selection', async () => {
    const defs = await readJson('Resources', 'metadata', 'entityDefs', 'AttendanceRecord.json');
    const view = await readFile(
        path.join(clientRoot, 'src', 'views', 'attendance', 'my.js'),
        'utf8'
    );
    const css = await readFile(path.join(clientRoot, 'css', 'attendance.css'), 'utf8');

    assert.equal(defs.fields.monthDate.type, 'date');
    assert.equal(defs.fields.monthDate.utility, true);
    assert.match(view, /'views\/fields\/date'/);
    assert.match(view, /data-month-field/);
    assert.doesNotMatch(view, /type="month"/);
    assert.match(css, /\.attendance-page\s*\{\s*width: 100%;/);
    assert.doesNotMatch(css, /max-width:\s*920px/);
});

test('attendance page and statuses are bilingual', async () => {
    for (const locale of ['en_US', 'ro_RO']) {
        const global = await readJson('Resources', 'i18n', locale, 'Global.json');
        const attendance = await readJson('Resources', 'i18n', locale, 'AttendanceRecord.json');

        assert.equal(typeof global.labels.Attendance, 'string');
        assert.equal(typeof global.labels.AttendanceOverview, 'string');
        assert.equal(typeof global.labels.AttendanceManagement, 'string');
        assert.equal(typeof attendance.options.status.AtWork, 'string');
        assert.equal(typeof attendance.options.status.Holiday, 'string');
        assert.equal(typeof attendance.options.status.BusinessTrip, 'string');
        assert.equal(typeof attendance.messages['Attendance Saved'], 'string');
        assert.equal(typeof attendance.labels['Attendance Overview'], 'string');
        assert.equal(typeof attendance.messages['Confirm Reminders'], 'string');

        const settings = await readJson('Resources', 'i18n', locale, 'Settings.json');
        const admin = await readJson('Resources', 'i18n', locale, 'Admin.json');
        assert.equal(typeof settings.fields.attendanceManagementManagers, 'string');
        assert.equal(typeof settings.fields.attendanceManagementScheduleEditor, 'string');
        assert.equal(typeof admin.descriptions.attendanceManagementSettings, 'string');
    }
});
