import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';

const extensionRoot = path.resolve(import.meta.dirname, '..', '..');
const moduleRoot = path.join(
    extensionRoot,
    'files', 'custom', 'Espo', 'Modules', 'HolidayManagement'
);

async function readJson(...segments) {
    return JSON.parse(await readFile(path.join(moduleRoot, ...segments), 'utf8'));
}

async function readModuleSource(...segments) {
    return readFile(path.join(moduleRoot, ...segments), 'utf8');
}

test('HolidayRequest is an owner-scoped calendar event with derived accounting fields', async () => {
    const defs = await readJson('Resources', 'metadata', 'entityDefs', 'HolidayRequest.json');
    const scope = await readJson('Resources', 'metadata', 'scopes', 'HolidayRequest.json');
    const entityAcl = await readJson('Resources', 'metadata', 'entityAcl', 'HolidayRequest.json');
    const controller = await readModuleSource('Controllers', 'HolidayRequest.php');

    assert.equal(defs.transactionalSave, true);
    assert.equal(defs.fields.dateStart.type, 'datetimeOptional');
    assert.equal(defs.fields.dateStartDate.type, 'date');
    assert.equal(defs.fields.dateStartDate.required, true);
    assert.equal(
        defs.fields.dateStartDate.view,
        'holiday-management:views/holiday-request/fields/start-date'
    );
    assert.equal(defs.fields.dateEndDate.afterOrEqual, true);
    assert.equal(defs.fields.days.readOnly, true);
    assert.deepEqual(defs.fields.status.options, ['Pending', 'Approved', 'Rejected']);
    assert.equal(defs.fields.status.default, 'Pending');
    assert.equal(defs.fields.status.audited, true);
    assert.equal(defs.fields.decidedBy.audited, true);
    assert.equal(defs.fields.decidedAt.audited, true);
    assert.equal(defs.fields.assignedUser.readOnly, true);
    assert.equal(defs.fields.profile.readOnly, true);
    assert.equal(defs.indexes.accountingKeyUnique.unique, true);
    assert.equal(scope.type, 'Event');
    assert.equal(scope.calendar, true);
    assert.equal(scope.tab, true);
    assert.equal(scope.aclPortal, false);
    assert.match(controller, /extends Record/);
    assert.match(controller, /fetchSearchParamsFromRequest/);
    assert.match(controller, /->withWhereAdded\(WhereItem::fromRaw/);
    assert.match(controller, /'attribute'\s*=>\s*'assignedUserId'/);
    assert.match(controller, /'value'\s*=>\s*\$this->user->getId\(\)/);

    const listLayout = await readJson('Resources', 'layouts', 'HolidayRequest', 'list.json');
    const searchLayout = await readJson('Resources', 'layouts', 'HolidayRequest', 'search.json');
    assert.equal(listLayout.some(item => item.name === 'assignedUser'), false);
    assert.equal(listLayout.some(item => item.name === 'status'), true);
    assert.equal(searchLayout.includes('assignedUser'), false);

    for (const field of [
        'name', 'days', 'status', 'decidedBy', 'decidedAt', 'assignedUser',
        'profile', 'accountingKey', 'accountingRevision',
    ]) {
        assert.equal(entityAcl.fields[field].readOnly, true, `${field} must be server-managed`);
    }
});

test('all internal users can read shared requests but mutate only their own', async () => {
    const acl = await readJson('Resources', 'metadata', 'app', 'acl.json');
    const scope = await readJson('Resources', 'metadata', 'scopes', 'HolidayRequest.json');
    const auxiliaryNavbar = await readJson('Resources', 'metadata', 'app', 'clientNavbar.json');
    const access = acl.mandatory.scopeLevel.HolidayRequest;
    const afterInstall = await readFile(path.join(extensionRoot, 'scripts', 'AfterInstall.php'), 'utf8');

    assert.equal(acl.mandatory.scopeLevel.Calendar, true);
    assert.deepEqual(access, {
        create: 'yes',
        read: 'all',
        edit: 'own',
        delete: 'own',
    });
    assert.equal(scope.tab, true);
    assert.deepEqual(auxiliaryNavbar.menuItems, {});
    assert.match(afterInstall, /tabList/);
    assert.match(afterInstall, /NAVIGATION_ENTITY\s*=\s*'HolidayRequest'/);
});

test('request lifecycle hooks reserve, adjust, and refund the profile balance', async () => {
    const recordDefs = await readJson('Resources', 'metadata', 'recordDefs', 'HolidayRequest.json');
    const balanceSource = await readModuleSource(
        'Tools', 'HolidayBalance', 'HolidayBalanceService.php'
    );

    assert.deepEqual(recordDefs.earlyBeforeCreateHookClassNameList, [
        'Espo\\Modules\\HolidayManagement\\Classes\\Record\\Hooks\\HolidayRequest\\BeforeCreate',
    ]);
    assert.deepEqual(recordDefs.earlyBeforeUpdateHookClassNameList, [
        'Espo\\Modules\\HolidayManagement\\Classes\\Record\\Hooks\\HolidayRequest\\BeforeUpdate',
    ]);
    assert.equal(recordDefs.relationships.profile.linkForeignAccessCheckDisabled, true);
    assert.equal(recordDefs.massActions.update.disabled, true);
    assert.equal(recordDefs.massActions.delete.disabled, false);

    for (const method of [
        'getMyBalance', 'prepareHolidayForCreate', 'reserveHoliday',
        'prepareHolidayForUpdate', 'adjustHoliday', 'cancelHoliday',
        'getApprovalState', 'decideHoliday', 'processApprovalDecision',
    ]) {
        assert.match(balanceSource, new RegExp(`public function ${method}\\(`));
    }

    assert.match(balanceSource, /->forUpdate\(\)/);
    assert.match(balanceSource, /assertNoRequestOverlap/);
    assert.match(balanceSource, /assertBookingDatesAllowed/);
    assert.match(balanceSource, /getToday\(\)->toString\(\)/);
    assert.match(balanceSource, /holidayManagementNegativeBalanceLimitDays/);
    assert.match(balanceSource, /holidayBooked/);
    assert.match(balanceSource, /holidayAdjusted/);
    assert.match(balanceSource, /holidayCancelled/);
    assert.match(balanceSource, /holidayRejected/);
    assert.match(balanceSource, /holidayManagementApproversIds/);
    assert.match(balanceSource, /An approver cannot decide their own holiday request/);
    assert.match(balanceSource, /currentStatus !== self::STATUS_PENDING/);
    assert.match(balanceSource, /getTransactionManager\(\)->run/);
    assert.match(
        balanceSource,
        /This %s %s %s, but only %s are available\. Requested: %s days; available: %s days; shortfall: %s days\./
    );
    assert.match(balanceSource, /\$isAdjustment \? 'change requires' : 'booking requires'/);
    assert.match(balanceSource, /max\(0\.0, \$currentBalance - \$limit\)/);
    assert.match(balanceSource, /max\(0\.0, \$daysToDeduct - \$availableDays\)/);
    assert.doesNotMatch(balanceSource, /minimum allowed balance/);

    const repositoryHook = await readModuleSource('Hooks', 'HolidayRequest', 'Balance.php');
    assert.match(repositoryHook, /implements BeforeSave, BeforeRemove/);
    assert.match(repositoryHook, /->reserveHoliday\(/);
    assert.match(repositoryHook, /->adjustHoliday\(/);
    assert.match(repositoryHook, /->cancelHoliday\(/);
    assert.match(repositoryHook, /->processApprovalDecision\(/);
});

test('either configured approver can make the single final decision', async () => {
    const routes = await readJson('Resources', 'routes.json');
    const getApproval = await readModuleSource(
        'Tools', 'HolidayRequest', 'Api', 'GetApproval.php'
    );
    const postDecision = await readModuleSource(
        'Tools', 'HolidayRequest', 'Api', 'PostDecision.php'
    );
    const approvalActions = await readFile(path.join(
        extensionRoot,
        'files', 'client', 'custom', 'modules', 'holiday-management',
        'src', 'views', 'holiday-request', 'record', 'approval-actions.js'
    ), 'utf8');
    const ledger = await readJson('Resources', 'metadata', 'entityDefs', 'HolidayLedger.json');

    assert.ok(routes.some(item =>
        item.route === '/HolidayManagement/requests/:id/approval' &&
        item.method === 'get'
    ));
    assert.ok(routes.some(item =>
        item.route === '/HolidayManagement/requests/:id/decision' &&
        item.method === 'post'
    ));
    assert.match(getApproval, /getApprovalState\(\$id\)/);
    assert.match(postDecision, /decideHoliday\(\$id, \$data->decision\)/);
    assert.match(approvalActions, /decision === 'Approved'/);
    assert.match(approvalActions, /decide\(view, 'Rejected'/);
    assert.match(approvalActions, /state\.canDecide/);
    assert.match(approvalActions, /zile-sarbatoare:calendar-refresh/);
    assert.ok(ledger.fields.type.options.includes('holidayRejected'));
});

test('calendar query shows all users and supports multi-day overlap', async () => {
    const source = await readModuleSource('Services', 'HolidayRequest.php');
    const calendar = await readJson('Resources', 'metadata', 'clientDefs', 'Calendar.json');
    const requestClient = await readJson('Resources', 'metadata', 'clientDefs', 'HolidayRequest.json');
    const editSource = await readFile(path.join(
        extensionRoot,
        'files', 'client', 'custom', 'modules', 'holiday-management',
        'src', 'views', 'holiday-request', 'record', 'edit-small.js'
    ), 'utf8');
    const calendarView = await readFile(path.join(
        extensionRoot,
        'files', 'client', 'custom', 'modules', 'holiday-management',
        'src', 'views', 'calendar', 'calendar.js'
    ), 'utf8');
    const calendarCss = await readFile(path.join(
        extensionRoot,
        'files', 'client', 'custom', 'modules', 'holiday-management',
        'css', 'calendar.css'
    ), 'utf8');
    const nonWorkingDayProvider = await readModuleSource(
        'Tools', 'HolidayRequest', 'NonWorkingDayProvider.php'
    );
    const client = await readJson('Resources', 'metadata', 'app', 'client.json');
    const module = await readJson('Resources', 'module.json');

    assert.match(source, /getCalenderQuery/);
    assert.match(source, /withStrictAccessControl\(\)/);
    assert.doesNotMatch(source, /'assignedUserName'/);
    assert.doesNotMatch(source, /'assignedUserId'\s*=>\s*\$userId/);
    assert.match(source, /'dateStartDate<'/);
    assert.match(source, /'dateEndDate>='/);
    assert.match(source, /\['status', 'status'\]/);
    assert.match(source, /\['status!='\s*=>\s*'Rejected'\]/);
    assert.match(source, /\['status'\s*=>\s*null\]/);
    assert.equal(calendar.colors.HolidayRequest, '#4F8A8B');
    assert.equal(
        calendar.calendarView,
        'holiday-management:views/calendar/calendar'
    );
    assert.match(calendarView, /holiday-request-marker/);
    assert.match(calendarView, /-marker-\$\{dateString\}/);
    assert.match(calendarView, /event\.title = `\\u2602 \$\{userName\}`/);
    assert.match(calendarView, /replace\(\/\^Holiday - \/, ''\)/);
    assert.doesNotMatch(calendarView, /display:\s*'background'/);
    assert.match(calendarCss, /width:\s*max-content/);
    assert.match(calendarCss, /min-width:\s*24px/);
    assert.match(calendarCss, /white-space:\s*nowrap/);
    assert.doesNotMatch(calendarCss, /(?<!min-)width:\s*24px/);
    assert.match(nonWorkingDayProvider, /ENTITY_TYPE\s*=\s*'ZileLibere'/);
    assert.match(nonWorkingDayProvider, /COUNTRY_CODE\s*=\s*'RO'/);
    assert.match(nonWorkingDayProvider, /'dateStart>='/);
    assert.match(nonWorkingDayProvider, /'dateStart<='/);
    assert.equal(module.order, 110);
    assert.deepEqual(client.cssList, [
        '__APPEND__',
        'client/custom/modules/holiday-management/css/calendar.css',
    ]);
    assert.equal(
        requestClient.recordViews.detail,
        'holiday-management:views/holiday-request/record/detail'
    );
    assert.equal(
        requestClient.recordViews.detailSmall,
        'holiday-management:views/holiday-request/record/detail-small'
    );
    assert.equal(
        requestClient.recordViews.editSmall,
        'holiday-management:views/holiday-request/record/edit-small'
    );
    assert.match(editSource, /copyCalendarDate\('dateStart', 'dateStartDate'\)/);
    assert.match(editSource, /copyCalendarDate\('dateEnd', 'dateEndDate'\)/);
});

test('self-service page fetches only the signed-in balance and presents bilingual copy', async () => {
    const routes = await readJson('Resources', 'routes.json');
    const action = await readModuleSource('Tools', 'HolidayRequest', 'Api', 'GetMyBalance.php');
    const client = await readFile(path.join(
        extensionRoot,
        'files', 'client', 'custom', 'modules', 'holiday-management',
        'src', 'views', 'holiday-request', 'list.js'
    ), 'utf8');
    const route = routes.find(item => item.route === '/HolidayManagement/myBalance');

    assert.deepEqual(route, {
        route: '/HolidayManagement/myBalance',
        method: 'get',
        actionClassName: 'Espo\\Modules\\HolidayManagement\\Tools\\HolidayRequest\\Api\\GetMyBalance',
    });
    assert.match(action, /getMyBalance\(\)/);
    assert.doesNotMatch(action, /getParsedBody|getQueryParam/);
    assert.match(client, /HolidayManagement\/myBalance/);
    assert.match(client, /balance\.balance/);
    assert.match(client, /annualEntitlement/);
    assert.match(client, /catch \(error\)/);

    for (const locale of ['en_US', 'ro_RO']) {
        const global = await readJson('Resources', 'i18n', locale, 'Global.json');
        const request = await readJson('Resources', 'i18n', locale, 'HolidayRequest.json');

        assert.equal(typeof global.labels['My Holiday'], 'string');
        assert.equal(typeof global.scopeNames.HolidayRequest, 'string');
        assert.equal(typeof request.messages.holidayBalanceSummary, 'string');
        assert.equal(typeof request.labels['Profile Not Ready'], 'string');
        assert.equal(typeof request.fields.status, 'string');
        assert.equal(typeof request.options.status.Pending, 'string');
        assert.equal(typeof request.messages.confirmApproveHoliday, 'string');
        assert.equal(typeof request.messages.confirmRejectHoliday, 'string');
    }
});

test('install and uninstall hooks safely register the request calendar entity and main tab', async () => {
    const afterInstall = await readFile(path.join(extensionRoot, 'scripts', 'AfterInstall.php'), 'utf8');
    const beforeUninstall = await readFile(path.join(extensionRoot, 'scripts', 'BeforeUninstall.php'), 'utf8');

    assert.match(afterInstall, /calendarEntityList/);
    assert.match(afterInstall, /tabList/);
    assert.match(afterInstall, /HolidayRequest/);
    assert.match(afterInstall, /in_array\([^;]+true\)/s);
    assert.match(beforeUninstall, /calendarEntityList/);
    assert.match(beforeUninstall, /tabList/);
    assert.match(beforeUninstall, /HolidayRequest/);
    assert.doesNotMatch(beforeUninstall, /removeEntity|DELETE|DROP/i);
});
