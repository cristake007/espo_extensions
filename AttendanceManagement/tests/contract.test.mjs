import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import vm from 'node:vm';

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

function flattenTranslationKeys(value, prefix = '') {
    return Object.entries(value).flatMap(([key, child]) => {
        const path = prefix ? `${prefix}.${key}` : key;

        return child && typeof child === 'object' && !Array.isArray(child)
            ? flattenTranslationKeys(child, path)
            : [path];
    });
}

test('manifest packages a standalone EspoCRM 10 attendance module', async () => {
    const manifest = JSON.parse(await readFile(
        path.join(extensionRoot, 'manifest.json'),
        'utf8'
    ));
    const module = await readJson('Resources', 'module.json');

    assert.equal(manifest.name, 'Attendance Management');
    assert.equal(manifest.version, '1.10.13');
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
    assert.match(service, /'availableFromMonth' => \$availableFromMonth/);
    assert.match(service, /order\('date', 'ASC'\)/);
    assert.match(service, /order\('effectiveFrom', 'ASC'\)/);
    assert.match(service, /Attendance month predates the available archive/);
    assert.match(service, /attendanceManagementEditablePastMonths/);
    assert.match(service, /'isLocked' => \$isLocked/);
    assert.match(service, /Attendance for this month is locked/);
    assert.match(service, /substr\(\$date, 0, 7\) < \$this->getEditableFromMonth\(\$today\)/);
    assert.match(service, /User::TYPE_REGULAR, User::TYPE_ADMIN/);
});

test('manager API is protected by the native EspoCRM role ACL', async () => {
    const routes = await readJson('Resources', 'routes.json');
    const checker = await readSource('Tools', 'Attendance', 'AttendanceAccessChecker.php');
    const settings = await readJson('Resources', 'metadata', 'entityDefs', 'Settings.json');
    const scope = await readJson('Resources', 'metadata', 'scopes', 'AttendanceOverview.json');
    const clientNavbar = await readJson('Resources', 'metadata', 'app', 'clientNavbar.json');

    assert.deepEqual(routes.slice(2).map(item => [item.route, item.method]), [
        ['/AttendanceManagement/overview', 'get'],
        ['/AttendanceManagement/overview/remind', 'post'],
        ['/AttendanceManagement/schedules', 'get'],
        ['/AttendanceManagement/settings', 'get'],
        ['/AttendanceManagement/settings', 'put'],
        ['/AttendanceManagement/settings/initialize', 'post'],
        ['/AttendanceManagement/overview/schedule', 'post'],
        ['/AttendanceManagement/overview/xlsx', 'post'],
        ['/AttendanceManagement/overview/pdf', 'post'],
    ]);
    assert.equal(settings.fields.attendanceManagementManagers, undefined);
    assert.equal(settings.fields.attendanceManagementEditablePastMonths.type, 'int');
    assert.equal(settings.fields.attendanceManagementEditablePastMonths.default, 1);
    assert.equal(settings.fields.attendanceManagementEditablePastMonths.min, 0);
    assert.equal(settings.fields.attendanceManagementEditablePastMonths.max, 120);
    assert.equal(settings.fields.attendanceManagementAllowIncompleteExports.type, 'bool');
    assert.equal(settings.fields.attendanceManagementAllowIncompleteExports.default, false);
    assert.equal(settings.fields.attendanceManagementArchiveInitializer.notStorable, true);
    assert.match(settings.fields.attendanceManagementArchiveInitializer.view, /archive-initializer/);
    assert.equal(scope.acl, 'boolean');
    assert.equal(clientNavbar.menuItems.admin.accessDataList[0].scope, 'AttendanceOverview');
    assert.match(checker, /private Acl \$acl/);
    assert.match(checker, /acl->check\('AttendanceOverview'\)/);
    assert.doesNotMatch(checker, /attendanceManagementManagersIds/);
    assert.doesNotMatch(checker, /holidayManagementApproversIds/);
    assert.match(checker, /assertManager/);
    assert.match(checker, /User::TYPE_REGULAR, User::TYPE_ADMIN/);
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
    assert.match(service, /'monthEditable' => \$month >= \$editableFromMonth/);
    assert.match(service, /'availableFromMonth' => \$availableFromMonth/);
    assert.match(service, /order\('date', 'ASC'\)/);
    assert.match(service, /order\('effectiveFrom', 'ASC'\)/);
    assert.match(service, /attendanceManagementAllowIncompleteExports/);
    assert.match(service, /'registerComplete' => \$registerComplete/);
    assert.match(service, /'incompleteExportsAllowed' => \$incompleteExportsAllowed/);
    assert.match(service, /\(\$registerComplete \|\| \$incompleteExportsAllowed\)/);
    assert.match(service, /Reminders cannot be sent for a locked attendance month/);
    assert.match(service, /'type' => User::TYPE_REGULAR/);
    assert.doesNotMatch(service, /User::TYPE_ADMIN/);
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
    assert.match(checker, /assertManager\(\)/);
    assert.doesNotMatch(service, /effectiveFrom<=/);
    assert.match(service, /order\('effectiveFrom', 'DESC'\)/);
    assert.match(service, /Working schedule times must use the HH:MM format/);
    assert.match(service, /end time must be after the start time/);
    assert.equal(settings.fields.attendanceManagementScheduleEditor.notStorable, true);
    assert.match(settings.fields.attendanceManagementScheduleEditor.view, /work-schedules/);
    assert.match(settingsView, /AttendanceManagement\/schedules/);
    assert.match(settingsView, /AttendanceManagement\/overview\/schedule/);
    assert.doesNotMatch(settingsView, /month:/);
    assert.doesNotMatch(settingsView, /type:\s*'time'/);
    assert.match(settingsView, /<select>/);
    assert.match(settingsView, /minutes \+= 15/);
    assert.match(settingsView, /!values\.includes\(value\)/);
    assert.match(settingsView, /attendance-schedule-row-dirty/);
    assert.match(settingsView, /data-action.*save-work-schedule/);
});

test('attendance role can manage only attendance-specific settings', async () => {
    const routes = await readJson('Resources', 'routes.json');
    const service = await readSource('Tools', 'Attendance', 'AttendanceSettingsService.php');
    const view = await readFile(
        path.join(clientRoot, 'src', 'views', 'attendance', 'schedules.js'),
        'utf8'
    );
    const initializer = await readFile(
        path.join(clientRoot, 'src', 'views', 'fields', 'archive-initializer.js'),
        'utf8'
    );

    assert.ok(routes.some(item =>
        item.route === '/AttendanceManagement/settings' && item.method === 'get'
    ));
    assert.ok(routes.some(item =>
        item.route === '/AttendanceManagement/settings' && item.method === 'put'
    ));
    assert.ok(routes.some(item =>
        item.route === '/AttendanceManagement/settings/initialize' && item.method === 'post'
    ));
    assert.match(service, /accessChecker->assertManager\(\)/);
    assert.match(service, /attendanceManagementEditablePastMonths/);
    assert.match(service, /attendanceManagementAllowIncompleteExports/);
    assert.match(service, /editablePastMonths < 0/);
    assert.match(service, /editablePastMonths > 120/);
    assert.match(service, /!is_bool\(\$allowIncompleteExports\)/);
    assert.match(service, /configWriter->setMultiple/);
    assert.match(service, /initializeArchive/);
    assert.match(service, /attendanceManagementArchiveStartMonth/);
    assert.match(service, /dateTime->getToday/);
    assert.doesNotMatch(service, /attendanceManagementManagers/);
    assert.match(view, /AttendanceManagement\/settings/);
    assert.match(view, /Ajax\.putRequest/);
    assert.match(view, /editablePastMonths/);
    assert.match(view, /allowIncompleteExports/);
    assert.match(view, /settings\/initialize/);
    assert.doesNotMatch(view, /type="month"/);
    assert.match(view, /AttendanceManagement\/schedules/);
    assert.match(initializer, /settings\/initialize/);
    assert.match(initializer, /Confirm Initialize Attendance/);
});

test('attendance managers can open only employee schedules at the administration URL', async () => {
    const clientRoutes = await readJson('Resources', 'metadata', 'app', 'clientRoutes.json');
    const controller = await readFile(
        path.join(clientRoot, 'src', 'controllers', 'schedule-settings.js'),
        'utf8'
    );
    const schedules = await readFile(
        path.join(clientRoot, 'src', 'views', 'attendance', 'schedules.js'),
        'utf8'
    );
    const overview = await readFile(
        path.join(clientRoot, 'src', 'views', 'attendance', 'overview.js'),
        'utf8'
    );
    const managerIndex = await readFile(
        path.join(clientRoot, 'src', 'views', 'admin', 'manager-index.js'),
        'utf8'
    );
    const adminRoute = clientRoutes.Admin;
    const route = clientRoutes['Admin/attendanceManagementSettings'];

    assert.equal(adminRoute.params.action, 'index');
    assert.ok(adminRoute.order < 1);
    assert.equal(route.params.controller, 'attendance-management:controllers/schedule-settings');
    assert.equal(route.params.action, 'open');
    assert.ok(route.order < 1);
    assert.match(controller, /dispatch\('Admin', 'index'\)/);
    assert.doesNotMatch(controller, /dispatch\('Admin', 'page'/);
    assert.match(controller, /getAcl\(\)\.check\('AttendanceOverview'\)/);
    assert.doesNotMatch(controller, /AttendanceManagement\/overview\/access/);
    assert.match(controller, /Exceptions\.AccessDenied/);
    assert.match(controller, /views\/attendance\/schedules/);
    assert.match(schedules, /AttendanceManagement\/schedules/);
    assert.match(schedules, /AttendanceManagement\/overview\/schedule/);
    assert.doesNotMatch(schedules, /attendanceManagementManagers/);
    assert.match(overview, /#Admin\/attendanceManagementSettings/);
    assert.match(managerIndex, /Attendance Management/);
    assert.match(managerIndex, /Attendance Settings/);
    assert.match(managerIndex, /#Admin\/attendanceManagementSettings/);

    let ScheduleSettingsController;
    let aclAllowed = true;
    class Controller {}
    class AccessDenied extends Error {}

    vm.runInNewContext(controller, {
        define: (dependencies, factory) => {
            ScheduleSettingsController = factory(Controller);
        },
        Espo: {
            Exceptions: {AccessDenied},
        },
    });

    const adminDispatches = [];
    const adminViews = [];
    const adminController = new ScheduleSettingsController();
    adminController.getUser = () => ({isAdmin: () => true});
    adminController.getAcl = () => ({check: () => true});
    adminController.main = (...args) => adminViews.push(args);
    adminController.getRouter = () => ({
        dispatch: (...args) => adminDispatches.push(args),
    });
    await adminController.actionOpen();
    assert.equal(adminDispatches.length, 0);
    assert.equal(
        adminViews[0][0],
        'attendance-management:views/attendance/schedules'
    );
    await adminController.actionIndex();
    assert.equal(adminDispatches[0][0], 'Admin');
    assert.equal(adminDispatches[0][1], 'index');

    const managerViews = [];
    const managerController = new ScheduleSettingsController();
    managerController.getUser = () => ({isAdmin: () => false});
    managerController.getAcl = () => ({check: () => aclAllowed});
    managerController.main = (...args) => managerViews.push(args);
    await managerController.actionOpen();
    assert.equal(
        managerViews[0][0],
        'attendance-management:views/attendance/schedules'
    );
    managerViews.length = 0;
    await managerController.actionIndex();
    assert.equal(
        managerViews[0][0],
        'attendance-management:views/admin/manager-index'
    );

    aclAllowed = false;
    await assert.rejects(() => managerController.actionOpen(), AccessDenied);
});

test('manager reminders create native EspoCRM notifications only for missing users', async () => {
    const service = await readSource('Tools', 'Attendance', 'AttendanceOverviewService.php');

    assert.match(service, /foreach \(\$overview\['missingUsers'\] as \$missingUser\)/);
    assert.match(service, /Notification::ENTITY_TYPE/);
    assert.match(service, /Notification::TYPE_MESSAGE/);
    assert.match(service, /'userId' => \$missingUser\['id'\]/);
    assert.match(service, /\[Deschide condica de prezenta\]\(#Attendance\/index\/month=%s\)/);
    assert.match(service, /\$overview\['month'\]/);
});

test('overview exports paginated A4 XLSX and PDF registers with schedule and no signature field', async () => {
    const generator = await readSource('Tools', 'Attendance', 'AttendanceXlsxGenerator.php');
    const pdfGenerator = await readSource('Tools', 'Attendance', 'AttendancePdfGenerator.php');
    const xlsxAction = await readSource('Tools', 'Attendance', 'Api', 'PostAttendanceXlsx.php');
    const pdfAction = await readSource('Tools', 'Attendance', 'Api', 'PostAttendancePdf.php');

    assert.match(generator, /PhpOffice\\PhpSpreadsheet\\Spreadsheet/);
    assert.match(generator, /EMPLOYEES_PER_PRINT_PAGE = 7/);
    assert.match(generator, /PRINT_BODY_ROW_HEIGHT = 25/);
    assert.match(generator, /PRINT_FONT_SIZE = 11/);
    assert.match(generator, /setRowHeight\(self::PRINT_BODY_ROW_HEIGHT\)/);
    assert.match(generator, /setSize\(self::PRINT_FONT_SIZE\)/);
    assert.match(generator, /ORIENTATION_PORTRAIT/);
    assert.match(generator, /setFitToWidth\(\$printPageCount\)/);
    assert.match(generator, /setFitToHeight\(1\)/);
    assert.match(generator, /setBreak\(\$breakColumn \. '1', Worksheet::BREAK_COLUMN\)/);
    assert.match(generator, /setColumnsToRepeatAtLeftByStartAndEnd\('A', 'B'\)/);
    assert.match(generator, /setRowsToRepeatAtTopByStartAndEnd\(3, 4\)/);
    assert.match(generator, /setPrintArea/);
    assert.match(generator, /Pagina &P din &N/);
    assert.match(generator, /setOddHeader\('&C&14&B' \. \$title\)/);
    assert.match(generator, /Condica de prezenta_%s %d\.xlsx/);
    assert.match(generator, /Ora intrare/);
    assert.match(generator, /Ora iesire/);
    assert.match(generator, /startTime/);
    assert.match(generator, /endTime/);
    assert.doesNotMatch(generator, /Semnatura|Semnătură|Signature/);
    assert.match(xlsxAction, /if \(!\$overview\['downloadReady'\]\)/);
    assert.match(xlsxAction, /base64_encode/);
    assert.match(pdfGenerator, /Dompdf\\Dompdf/);
    assert.match(pdfGenerator, /EMPLOYEES_PER_PAGE = 7/);
    assert.match(pdfGenerator, /setPaper\('A4', 'portrait'\)/);
    assert.match(pdfGenerator, /array_chunk\(array_keys\(\$users\), self::EMPLOYEES_PER_PAGE\)/);
    assert.match(pdfGenerator, /Pagina \{PAGE_NUM\} din \{PAGE_COUNT\}/);
    assert.match(pdfGenerator, /%PDF-/);
    assert.doesNotMatch(pdfGenerator, /Semnatura|Semnătură|Signature/);
    assert.match(pdfAction, /if \(!\$overview\['downloadReady'\]\)/);
    assert.match(pdfAction, /application\/pdf/);
    assert.match(pdfAction, /base64_encode/);
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

test('personal page clearly separates today actions from the structured monthly register', async () => {
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
    assert.match(controller, /actionIndex\(options = \{\}\)/);
    assert.match(afterInstall, /NAVIGATION_SCOPE_LIST = \['Attendance', 'AttendanceOverview'\]/);
    assert.match(afterInstall, /'type' => 'group'/);
    assert.match(afterInstall, /'id' => self::NAVIGATION_GROUP_ID/);
    assert.match(afterInstall, /'itemList' => self::NAVIGATION_SCOPE_LIST/);
    assert.match(view, /data-status="AtWork"/);
    assert.match(view, /data-status="BusinessTrip"/);
    assert.match(view, /translateOption 'AtWork' field='status' scope='AttendanceRecord'/);
    assert.match(view, /translateOption 'BusinessTrip' field='status' scope='AttendanceRecord'/);
    assert.match(view, /getLanguage\(\)\.translateOption\(status, 'status', 'AttendanceRecord'\)/);
    assert.doesNotMatch(view, /translate 'AtWork' category='options'/);
    assert.doesNotMatch(view, /this\.translate\(status, 'options'/);
    assert.doesNotMatch(view, /data-status-indicator="Holiday"/);
    assert.match(view, /for \(const status of \['AtWork', 'BusinessTrip'\]\)/);
    assert.match(view, /prop\('disabled', !data.todayCanMark\)/);
    assert.match(view, /attendance-today-guidance/);
    assert.match(view, /attendance-month-summary/);
    assert.match(view, /attendance-my-table/);
    assert.match(view, /if \(day.canMark\)/);
    assert.match(view, /day.isLocked/);
    assert.match(view, /data-month-select/);
    assert.match(view, /translate 'Month' category='labels'/);
    assert.doesNotMatch(view, /translate 'monthDate'/);
    assert.match(view, /change \[data-month-select\]/);
    assert.match(view, /actionSelectMonth/);
    assert.doesNotMatch(view, /data-action="open-month"/);
    assert.match(view, /Managed Automatically/);
    assert.match(view, /displayWeekday/);
    assert.match(controller, /month: options\.month \|\| null/);
    assert.match(view, /loadAttendance\(this\.options\.month \|\| null\)/);
});

test('manager page uses native ACL navigation and provides matrix, reminders and exports', async () => {
    const overview = await readFile(
        path.join(clientRoot, 'src', 'views', 'attendance', 'overview.js'),
        'utf8'
    );
    const css = await readFile(path.join(clientRoot, 'css', 'attendance.css'), 'utf8');
    const scope = await readJson('Resources', 'metadata', 'scopes', 'AttendanceOverview.json');
    const client = await readJson('Resources', 'metadata', 'app', 'client.json');
    const clientNavbar = await readJson('Resources', 'metadata', 'app', 'clientNavbar.json');

    assert.equal(scope.tab, true);
    assert.equal(scope.acl, 'boolean');
    assert.equal(clientNavbar.menuItems.admin.accessDataList[0].scope, 'AttendanceOverview');
    assert.equal(client.scriptList, undefined);
    assert.match(overview, /attendance-overview-table/);
    assert.match(overview, /send-reminders/);
    assert.match(overview, /download-xlsx/);
    assert.match(overview, /download-pdf/);
    assert.match(overview, /AttendanceManagement\/overview\/remind/);
    assert.match(overview, /AttendanceManagement\/overview\/\$\{format\}/);
    assert.match(overview, /actionDownloadPdf/);
    assert.match(overview, /registerComplete/);
    assert.match(overview, /Incomplete Export Detail/);
    assert.match(overview, /attendance-schedule-value/);
    assert.match(overview, /data-overview-month-select/);
    assert.match(overview, /translate 'Month' category='labels'/);
    assert.doesNotMatch(overview, /translate 'monthDate'/);
    assert.match(overview, /change \[data-overview-month-select\]/);
    assert.match(overview, /availableFromMonth/);
    assert.match(overview, /while \(true\)/);
    assert.doesNotMatch(overview, /offset < 3/);
    assert.doesNotMatch(overview, /data-action="open-month"/);
    assert.doesNotMatch(overview, /'views\/fields\/date'/);
    assert.match(overview, /completionDetails/);
    assert.match(overview, /missingDetails/);
    assert.match(overview, /scheduleDetails/);
    assert.match(overview, /readinessReasons/);
    assert.match(overview, /attendance-summary-detail/);
    assert.doesNotMatch(overview, /data-schedule-field/);
    assert.match(overview, /translate\('Not Marked', 'labels', 'AttendanceRecord'\)/);
    assert.match(overview, /getLanguage\(\)\.translateOption\(status, 'status', 'AttendanceRecord'\)/);
    assert.doesNotMatch(overview, /this\.translate\(status, 'options'/);
    assert.doesNotMatch(css, /attendance-management-manager/);
});

test('page is full-width and uses an immediate month dropdown', async () => {
    const defs = await readJson('Resources', 'metadata', 'entityDefs', 'AttendanceRecord.json');
    const view = await readFile(
        path.join(clientRoot, 'src', 'views', 'attendance', 'my.js'),
        'utf8'
    );
    const css = await readFile(path.join(clientRoot, 'css', 'attendance.css'), 'utf8');

    assert.equal(defs.fields.monthDate, undefined);
    assert.match(view, /<select class="form-control" data-month-select>/);
    assert.match(view, /availableFromMonth/);
    assert.match(view, /while \(true\)/);
    assert.doesNotMatch(view, /offset < 3/);
    assert.match(view, /change \[data-month-select\]/);
    assert.doesNotMatch(view, /'views\/fields\/date'/);
    assert.match(css, /\.attendance-page\s*\{\s*width: 100%;/);
    assert.doesNotMatch(css, /max-width:\s*920px/);
});

test('attendance page and statuses are bilingual', async () => {
    const settingsView = await readFile(
        path.join(clientRoot, 'src', 'views', 'admin', 'settings.js'),
        'utf8'
    );
    const tabLabels = [...settingsView.matchAll(/tabLabel:\s*'([^']+)'/g)]
        .map(match => match[1]);

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
        assert.equal(typeof attendance.labels.Month, 'string');
        assert.equal(typeof attendance.messages['Confirm Reminders'], 'string');
        assert.equal(typeof attendance.messages['Attendance Page Guide'], 'string');
        assert.equal(typeof attendance.messages['Today Marking Guide'], 'string');
        assert.equal(typeof attendance.messages['Monthly Register Guide'], 'string');
        assert.equal(typeof attendance.labels['Today Attendance'], 'string');
        assert.equal(typeof attendance.labels['Choose Attendance'], 'string');
        assert.equal(typeof attendance.labels.Completion, 'string');
        assert.equal(typeof attendance.labels['Missing Attendance'], 'string');
        assert.equal(typeof attendance.labels['Schedule Coverage'], 'string');
        assert.equal(typeof attendance.labels['Register Readiness'], 'string');
        assert.equal(typeof attendance.labels['Download PDF'], 'string');
        assert.equal(typeof attendance.labels['Export Available'], 'string');
        assert.equal(typeof attendance.messages['Completion Detail'], 'string');
        assert.equal(typeof attendance.messages['Daily Matrix Guide'], 'string');
        assert.equal(typeof attendance.messages['Locked Month Reminder'], 'string');
        assert.equal(typeof attendance.messages['Readiness Month Locked'], 'string');
        assert.equal(typeof attendance.labels['Employee Schedules'], 'string');
        assert.equal(typeof attendance.labels['Editable Previous Months'], 'string');
        assert.equal(typeof attendance.labels['Allow Incomplete Exports'], 'string');
        assert.equal(typeof attendance.labels['Save Settings'], 'string');
        assert.equal(typeof attendance.labels['Initialize Attendance'], 'string');
        assert.equal(typeof attendance.labels['Select Time'], 'string');
        assert.equal(typeof attendance.messages['Schedule Editor Guide'], 'string');
        assert.equal(typeof attendance.messages['Editable Previous Months Help'], 'string');
        assert.equal(typeof attendance.messages['Allow Incomplete Exports Help'], 'string');
        assert.equal(typeof attendance.messages['Settings Load Failed'], 'string');
        assert.equal(typeof attendance.messages['Invalid Settings'], 'string');
        assert.equal(typeof attendance.messages['Settings Saved'], 'string');
        assert.equal(typeof attendance.messages['Settings Save Failed'], 'string');
        assert.equal(typeof attendance.messages['Confirm Initialize Attendance'], 'string');
        assert.equal(typeof attendance.messages['Attendance Initialized'], 'string');

        const settings = await readJson('Resources', 'i18n', locale, 'Settings.json');
        const admin = await readJson('Resources', 'i18n', locale, 'Admin.json');
        assert.equal(typeof settings.fields.attendanceManagementEditablePastMonths, 'string');
        assert.equal(typeof settings.fields.attendanceManagementAllowIncompleteExports, 'string');
        assert.equal(typeof settings.fields.attendanceManagementScheduleEditor, 'string');
        assert.equal(typeof settings.fields.attendanceManagementArchiveInitializer, 'string');
        tabLabels.forEach(label => assert.equal(typeof settings.labels[label], 'string'));
        assert.equal(typeof admin.descriptions.attendanceManagementSettings, 'string');
        assert.equal(typeof admin.labels['Access and Editing'], 'string');
        assert.equal(typeof admin.labels['Employee Schedules'], 'string');
        assert.equal(typeof admin.labels['Attendance Settings'], 'string');
    }
});

test('Romanian locale covers every English attendance translation key', async () => {
    for (const file of ['Admin.json', 'AttendanceRecord.json', 'Global.json', 'Settings.json']) {
        const english = await readJson('Resources', 'i18n', 'en_US', file);
        const romanian = await readJson('Resources', 'i18n', 'ro_RO', file);

        assert.deepEqual(
            flattenTranslationKeys(romanian).sort(),
            flattenTranslationKeys(english).sort(),
            `${file} has incomplete Romanian translation coverage`
        );
    }
});

test('global-language attendance pages expose every record translation', async () => {
    for (const scopeName of ['Attendance', 'AttendanceOverview']) {
        const scope = await readJson('Resources', 'metadata', 'scopes', `${scopeName}.json`);

        assert.equal(scope.languageIsGlobal, true);
    }

    for (const locale of ['en_US', 'ro_RO']) {
        const record = await readJson('Resources', 'i18n', locale, 'AttendanceRecord.json');
        const global = await readJson('Resources', 'i18n', locale, 'Global.json');
        const globalKeys = new Set(flattenTranslationKeys(global));

        flattenTranslationKeys(record).forEach(key => assert.ok(
            globalKeys.has(key),
            `${locale} Global.json is missing ${key}`
        ));
    }
});
