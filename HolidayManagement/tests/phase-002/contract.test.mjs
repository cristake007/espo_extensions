import assert from 'node:assert/strict';
import {readFile, readdir} from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';

const extensionRoot = path.resolve(import.meta.dirname, '..', '..');
const moduleRoot = path.join(
    extensionRoot,
    'files', 'custom', 'Espo', 'Modules', 'HolidayManagement'
);

async function readJson(...segments) {
    return JSON.parse(await readFile(path.join(extensionRoot, ...segments), 'utf8'));
}

async function readModuleJson(...segments) {
    return JSON.parse(await readFile(path.join(moduleRoot, ...segments), 'utf8'));
}

async function readModuleSource(...segments) {
    return readFile(path.join(moduleRoot, ...segments), 'utf8');
}

test('HolidayProfile is unique per user and keeps service-managed accounting state', async () => {
    const defs = await readModuleJson('Resources', 'metadata', 'entityDefs', 'HolidayProfile.json');

    assert.deepEqual(defs.fields.user, {
        type: 'link',
        entity: 'User',
        required: true,
        readOnly: true,
    });
    assert.equal(defs.fields.annualEntitlement.type, 'float');
    assert.equal(defs.fields.balance.readOnly, true);
    assert.equal(defs.fields.nextResetDate.type, 'date');
    assert.equal(defs.fields.calendarColor.type, 'colorpicker');
    assert.equal(defs.fields.calendarColor.default, '#4F8A8B');
    assert.equal(defs.fields.isInitialized.readOnly, true);
    assert.equal(defs.fields.resetPending.readOnly, true);
    assert.equal(defs.fields.pendingResetDate.readOnly, true);
    assert.equal(defs.fields.pendingResetKey.readOnly, true);
    assert.deepEqual(defs.indexes.userUnique, {
        unique: true,
        columns: ['userId'],
    });
});

test('HolidayLedger stores immutable before/after audit data and a unique operation key', async () => {
    const defs = await readModuleJson('Resources', 'metadata', 'entityDefs', 'HolidayLedger.json');
    const requiredFields = [
        'profile', 'user', 'type', 'delta', 'balanceBefore', 'balanceAfter',
        'entitlementBefore', 'entitlementAfter', 'resetDateBefore', 'resetDateAfter',
        'actor', 'reason', 'effectiveDate', 'idempotencyKey',
    ];

    for (const field of requiredFields) {
        assert.ok(defs.fields[field], `missing ledger field ${field}`);
        assert.equal(defs.fields[field].readOnly, true, `${field} must be read-only`);
    }

    assert.deepEqual(defs.indexes.idempotencyKeyUnique, {
        unique: true,
        columns: ['idempotencyKey'],
    });
    assert.equal(defs.noDeletedAttribute, true);
});

test('profile and ledger are internal PHASE-002 entities with no standard write access', async () => {
    for (const entity of ['HolidayProfile', 'HolidayLedger']) {
        const scope = await readModuleJson('Resources', 'metadata', 'scopes', `${entity}.json`);
        const acl = await readModuleJson('Resources', 'metadata', 'aclDefs', `${entity}.json`);

        assert.equal(scope.entity, true);
        assert.equal(scope.module, 'HolidayManagement');
        assert.equal(scope.tab, false);
        assert.equal(scope.importable, false);
        assert.equal(scope.preserveAuditLog, true);
        assert.deepEqual(acl, {read: true, edit: false, delete: false, stream: false});
    }
});

test('approval remains field-based without extra response, notice, or document entities', async () => {
    const entityFiles = await readdir(path.join(moduleRoot, 'Resources', 'metadata', 'entityDefs'));
    const forbidden = [
        'CompanyHoliday.json',
        'HolidayRequestSegment.json',
        'HolidayApprovalResponse.json',
        'HolidayCalendar.json',
        'HolidayDocument.json',
        'Notification.json',
    ];

    for (const file of forbidden) {
        assert.equal(entityFiles.includes(file), false, `${file} belongs to a later phase`);
    }
});

test('record hooks block direct ledger writes and profile lifecycle mutations', async () => {
    const ledgerRecordDefs = await readModuleJson('Resources', 'metadata', 'recordDefs', 'HolidayLedger.json');
    const profileRecordDefs = await readModuleJson('Resources', 'metadata', 'recordDefs', 'HolidayProfile.json');

    assert.deepEqual(ledgerRecordDefs.beforeCreateHookClassNameList, [
        'Espo\\Modules\\HolidayManagement\\Classes\\Record\\Hooks\\HolidayLedger\\BeforeCreate',
    ]);
    assert.deepEqual(ledgerRecordDefs.beforeUpdateHookClassNameList, [
        'Espo\\Modules\\HolidayManagement\\Classes\\Record\\Hooks\\HolidayLedger\\BeforeUpdate',
    ]);
    assert.deepEqual(ledgerRecordDefs.beforeDeleteHookClassNameList, [
        'Espo\\Modules\\HolidayManagement\\Classes\\Record\\Hooks\\HolidayLedger\\BeforeDelete',
    ]);
    assert.equal(ledgerRecordDefs.massActions.update.disabled, true);
    assert.equal(ledgerRecordDefs.massActions.delete.disabled, true);

    assert.equal(profileRecordDefs.beforeCreateHookClassNameList.length, 1);
    assert.equal(profileRecordDefs.beforeUpdateHookClassNameList.length, 1);
    assert.equal(profileRecordDefs.beforeDeleteHookClassNameList.length, 1);
});

test('immutable hook implementations use EspoCRM 10 record interfaces and Forbidden errors', async () => {
    const cases = [
        ['HolidayLedger', 'BeforeCreate', 'CreateHook'],
        ['HolidayLedger', 'BeforeUpdate', 'UpdateHook'],
        ['HolidayLedger', 'BeforeDelete', 'DeleteHook'],
        ['HolidayProfile', 'BeforeCreate', 'CreateHook'],
        ['HolidayProfile', 'BeforeUpdate', 'UpdateHook'],
        ['HolidayProfile', 'BeforeDelete', 'DeleteHook'],
    ];

    for (const [entity, hook, interfaceName] of cases) {
        const source = await readModuleSource('Classes', 'Record', 'Hooks', entity, `${hook}.php`);

        assert.match(source, new RegExp(`implements\\s+${interfaceName}`));
        assert.match(source, /throw new Forbidden\(/);
    }

    const profileUpdate = await readModuleSource(
        'Classes', 'Record', 'Hooks', 'HolidayProfile', 'BeforeUpdate.php'
    );
    for (const field of [
        'annualEntitlement', 'balance', 'nextResetDate', 'isInitialized',
        'resetPending', 'pendingResetDate', 'pendingResetKey',
    ]) {
        assert.match(profileUpdate, new RegExp(`['"]${field}['"]`));
    }
    assert.match(profileUpdate, /isAttributeChanged/);
});

test('HolidayBalanceService serializes and de-duplicates every profile mutation', async () => {
    const source = await readModuleSource(
        'Tools', 'HolidayBalance', 'HolidayBalanceService.php'
    );

    for (const method of ['listProfiles', 'bulkInitialize', 'correct', 'reset']) {
        assert.match(source, new RegExp(`public function ${method}\\(`));
    }

    assert.match(source, /getTransactionManager\(\)->run\(/);
    assert.match(source, /->forUpdate\(\)/);
    assert.match(source, /findLedgerByKey/);
    assert.ok(
        (source.match(/findLedgerByKey\(/g) ?? []).length >= 7,
        'idempotency must be rechecked after acquiring mutation locks'
    );
    assert.match(source, /idempotencyKey/);
    assert.match(source, /User::TYPE_REGULAR/);
    assert.equal(
        (source.match(/User::TYPE_ADMIN/g) ?? []).length,
        1,
        'admins may operate internal workflows but must not receive holiday profiles'
    );
    assert.match(source, /'isActive'\s*=>\s*true/);
    assert.match(source, /isProfileForActiveRegularUser/);
    assert.match(source, /assertProfileForActiveRegularUser/);
});

test('service audits complete before and after state for initialization and corrections', async () => {
    const source = await readModuleSource(
        'Tools', 'HolidayBalance', 'HolidayBalanceService.php'
    );

    for (const field of [
        'delta', 'balanceBefore', 'balanceAfter', 'entitlementBefore',
        'entitlementAfter', 'resetDateBefore', 'resetDateAfter', 'actorId',
        'reason', 'effectiveDate', 'idempotencyKey',
    ]) {
        assert.match(source, new RegExp(`['"]${field}['"]`));
    }

    assert.match(source, /initialization/);
    assert.match(source, /bulkUpdate/);
    assert.match(source, /correction/);
    assert.match(source, /message\('correctionReasonRequired'\)/);
    assert.match(source, /\$balance\s*=\s*\$requiresOpeningBalance\s*\?\s*\$openingBalance/);
});

test('annual resets grant all days and warn without capping the balance', async () => {
    const source = await readModuleSource(
        'Tools', 'HolidayBalance', 'HolidayBalanceService.php'
    );

    assert.match(source, /BalanceMath::calculateResetBalance\(/);
    assert.match(source, /holidayManagementCarryOverLimitDays/);
    assert.match(source, /BalanceMath::exceedsWarningThreshold\(/);
    assert.match(source, /Notification::TYPE_MESSAGE/);
    assert.match(source, /balanceWarningEmployeeNotification/);
    assert.match(source, /balanceWarningApproverNotification/);
    assert.match(source, /holidayManagementApproversIds/);
    assert.match(source, /processDueResets/);
    assert.match(source, /holidayManagementAutomaticAnnualGrantEnabled/);
    assert.match(source, /nextResetDate<=/);
    assert.match(source, /scheduled-reset:/);
    assert.match(source, /resetOverride/);
    assert.match(source, /message\('forcedResetReasonRequired'\)/);
    assert.doesNotMatch(source, /holidayManagementResetCeilingDays/);
});

test('daily scheduled job processes due annual holiday resets', async () => {
    const scheduledJobs = await readModuleJson('Resources', 'metadata', 'app', 'scheduledJobs.json');
    const source = await readModuleSource('Jobs', 'ProcessHolidayResets.php');

    assert.deepEqual(scheduledJobs.ProcessHolidayResets, {
        jobClassName: 'Espo\\Modules\\HolidayManagement\\Jobs\\ProcessHolidayResets',
        scheduling: '5 0 * * *',
        isDefault: true,
    });
    assert.match(source, /implements JobDataLess/);
    assert.match(source, /->processDueResets\(\)/);
});

test('module exposes balance, self-service, and approval routes', async () => {
    const routes = await readModuleJson('Resources', 'routes.json');

    assert.deepEqual(routes, [
        {
            route: '/HolidayManagement/profiles',
            method: 'get',
            actionClassName: 'Espo\\Modules\\HolidayManagement\\Tools\\HolidayBalance\\Api\\GetProfiles',
        },
        {
            route: '/HolidayManagement/profiles/:id/corrections',
            method: 'get',
            actionClassName: 'Espo\\Modules\\HolidayManagement\\Tools\\HolidayBalance\\Api\\GetCorrectionHistory',
        },
        {
            route: '/HolidayManagement/bulkInitialize',
            method: 'post',
            actionClassName: 'Espo\\Modules\\HolidayManagement\\Tools\\HolidayBalance\\Api\\PostBulkInitialize',
        },
        {
            route: '/HolidayManagement/completeInitialSetup',
            method: 'post',
            actionClassName: 'Espo\\Modules\\HolidayManagement\\Tools\\HolidayBalance\\Api\\PostCompleteInitialSetup',
        },
        {
            route: '/HolidayManagement/testDataResetPreview',
            method: 'get',
            actionClassName: 'Espo\\Modules\\HolidayManagement\\Tools\\HolidayBalance\\Api\\GetTestDataResetPreview',
        },
        {
            route: '/HolidayManagement/testDataReset',
            method: 'post',
            actionClassName: 'Espo\\Modules\\HolidayManagement\\Tools\\HolidayBalance\\Api\\PostTestDataReset',
        },
        {
            route: '/HolidayManagement/correct',
            method: 'post',
            actionClassName: 'Espo\\Modules\\HolidayManagement\\Tools\\HolidayBalance\\Api\\PostCorrection',
        },
        {
            route: '/HolidayManagement/reset',
            method: 'post',
            actionClassName: 'Espo\\Modules\\HolidayManagement\\Tools\\HolidayBalance\\Api\\PostReset',
        },
        {
            route: '/HolidayManagement/myBalance',
            method: 'get',
            actionClassName: 'Espo\\Modules\\HolidayManagement\\Tools\\HolidayRequest\\Api\\GetMyBalance',
        },
        {
            route: '/HolidayManagement/managedBookingUsers',
            method: 'get',
            actionClassName: 'Espo\\Modules\\HolidayManagement\\Tools\\HolidayRequest\\Api\\GetManagedBookingUsers',
        },
        {
            route: '/HolidayManagement/managedBookings',
            method: 'post',
            actionClassName: 'Espo\\Modules\\HolidayManagement\\Tools\\HolidayRequest\\Api\\PostManagedBooking',
        },
        {
            route: '/HolidayManagement/requests/:id/approval',
            method: 'get',
            actionClassName: 'Espo\\Modules\\HolidayManagement\\Tools\\HolidayRequest\\Api\\GetApproval',
        },
        {
            route: '/HolidayManagement/requests/:id/decision',
            method: 'post',
            actionClassName: 'Espo\\Modules\\HolidayManagement\\Tools\\HolidayRequest\\Api\\PostDecision',
        },
        {
            route: '/HolidayManagement/requests/:id/cancellation-request',
            method: 'post',
            actionClassName: 'Espo\\Modules\\HolidayManagement\\Tools\\HolidayRequest\\Api\\PostCancellationRequest',
        },
        {
            route: '/HolidayManagement/requests/:id/cancel',
            method: 'post',
            actionClassName: 'Espo\\Modules\\HolidayManagement\\Tools\\HolidayRequest\\Api\\PostCancel',
        },
        {
            route: '/HolidayManagement/approvalQueue',
            method: 'get',
            actionClassName: 'Espo\\Modules\\HolidayManagement\\Tools\\HolidayRequest\\Api\\GetApprovalQueue',
        },
    ]);
});

test('profile setup and balance corrections are role-controlled while resets remain admin-only', async () => {
    const delegatedCases = [
        ['GetProfiles', 'listProfiles'],
        ['GetCorrectionHistory', 'listCorrections'],
        ['PostBulkInitialize', 'bulkInitialize'],
        ['PostCompleteInitialSetup', 'completeInitialSetup'],
    ];
    const adminCases = [['PostReset', 'reset']];

    for (const [className, serviceMethod] of delegatedCases) {
        const source = await readModuleSource(
            'Tools', 'HolidayBalance', 'Api', `${className}.php`
        );

        assert.match(source, /implements Action/);
        assert.match(source, /HolidayProfileAccessChecker/);
        assert.match(source, /accessChecker->assertManager\(\)/);
        assert.doesNotMatch(source, /->isAdmin\(\)/);
        assert.match(source, new RegExp(`->${serviceMethod}\\(`));
    }

    const correctionAction = await readModuleSource(
        'Tools', 'HolidayBalance', 'Api', 'PostCorrection.php'
    );
    const correctionAccessChecker = await readModuleSource(
        'Tools', 'HolidayBalance', 'HolidayBalanceCorrectionAccessChecker.php'
    );

    assert.match(correctionAction, /HolidayBalanceCorrectionAccessChecker/);
    assert.match(correctionAction, /accessChecker->assertManager\(\)/);
    assert.doesNotMatch(correctionAction, /->isAdmin\(\)/);
    assert.match(correctionAction, /->correct\(/);
    assert.match(correctionAccessChecker, /HolidayBalanceCorrection/);
    assert.match(correctionAccessChecker, /acl->check\(self::SCOPE\)/);
    assert.match(correctionAccessChecker, /user->isAdmin\(\)/);

    for (const [className, serviceMethod] of adminCases) {
        const source = await readModuleSource(
            'Tools', 'HolidayBalance', 'Api', `${className}.php`
        );

        assert.match(source, /implements Action/);
        assert.match(source, /->isAdmin\(\)/);
        assert.match(source, /throw new Forbidden\(/);
        assert.match(source, new RegExp(`->${serviceMethod}\\(`));
    }
});

test('Administration exposes a bulk profile setup view using the service endpoints', async () => {
    const adminPanel = await readModuleJson('Resources', 'metadata', 'app', 'adminPanel.json');
    const clientNavbar = await readModuleJson('Resources', 'metadata', 'app', 'clientNavbar.json');
    const clientRoutes = await readModuleJson('Resources', 'metadata', 'app', 'clientRoutes.json');
    const managementScope = await readModuleJson(
        'Resources', 'metadata', 'scopes', 'HolidayProfileManagement.json'
    );
    const correctionScope = await readModuleJson(
        'Resources', 'metadata', 'scopes', 'HolidayBalanceCorrection.json'
    );
    const item = adminPanel.holidayManagement.itemList.find(
        candidate => candidate.url === '#Admin/holidayManagementProfiles'
    );
    const source = await readFile(path.join(
        extensionRoot,
        'files', 'client', 'custom', 'modules', 'holiday-management',
        'src', 'views', 'admin', 'profiles.js'
    ), 'utf8');
    const requestList = await readFile(path.join(
        extensionRoot,
        'files', 'client', 'custom', 'modules', 'holiday-management',
        'src', 'views', 'holiday-request', 'list.js'
    ), 'utf8');
    const correctionModal = await readFile(path.join(
        extensionRoot,
        'files', 'client', 'custom', 'modules', 'holiday-management',
        'src', 'views', 'modals', 'balance-correction.js'
    ), 'utf8');
    const correctionHistoryModal = await readFile(path.join(
        extensionRoot,
        'files', 'client', 'custom', 'modules', 'holiday-management',
        'src', 'views', 'modals', 'correction-history.js'
    ), 'utf8');
    const initialSetupModal = await readFile(path.join(
        extensionRoot,
        'files', 'client', 'custom', 'modules', 'holiday-management',
        'src', 'views', 'modals', 'complete-initial-setup.js'
    ), 'utf8');
    const controller = await readFile(path.join(
        extensionRoot,
        'files', 'client', 'custom', 'modules', 'holiday-management',
        'src', 'controllers', 'holiday-profiles.js'
    ), 'utf8');
    const accessChecker = await readModuleSource(
        'Tools', 'HolidayBalance', 'HolidayProfileAccessChecker.php'
    );

    assert.ok(item, 'missing Holiday Profiles Administration item');
    assert.equal(item.view, 'modules/holiday-management/views/admin/profiles');
    assert.equal('recordView' in item, false);
    assert.equal(item.accessDataList[0].scope, 'HolidayProfileManagement');
    assert.equal(managementScope.acl, 'boolean');
    assert.equal(managementScope.tab, false);
    assert.equal(correctionScope.acl, 'boolean');
    assert.equal(correctionScope.tab, false);
    assert.equal('holidayProfiles' in clientNavbar.menuItems, false);
    assert.match(requestList, /getAcl\(\)\.check\('HolidayProfileManagement'\)/);
    assert.match(requestList, /#Admin\/holidayManagementProfiles/);
    assert.match(requestList, /addMenuItem\('buttons'/);
    assert.equal(
        clientRoutes['Admin/holidayManagementProfiles'].params.controller,
        'holiday-management:controllers/holiday-profiles'
    );
    assert.match(controller, /getAcl\(\)\.check\('HolidayProfileManagement'\)/);
    assert.match(controller, /Espo\.Exceptions\.AccessDenied/);
    assert.match(controller, /holiday-management:views\/admin\/profiles/);
    assert.match(accessChecker, /User::TYPE_REGULAR, User::TYPE_ADMIN/);
    assert.match(accessChecker, /user->isAdmin\(\)/);
    assert.match(accessChecker, /acl->check\(self::SCOPE\)/);
    assert.match(accessChecker, /profilesAccessForbidden/);
    assert.match(source, /HolidayManagement\/profiles/);
    assert.match(source, /HolidayManagement\/bulkInitialize/);
    assert.match(source, /actionInitializeProfile/);
    assert.match(source, /actionCompleteInitialSetup/);
    assert.match(source, /fetchedAnnualEntitlement\s*\?\?\s*row\.annualEntitlement/);
    assert.match(source, /fetchedNextResetDate\s*\|\|\s*row\.nextResetDate/);
    assert.match(source, /if \(!profileRow\.isInitialized\)/);
    assert.match(source, /annualEntitlement/);
    assert.match(source, /openingBalance/);
    assert.match(source, /HolidayBalanceCorrection/);
    assert.match(source, /mode: row\.isInitialized \? 'list' : 'edit'/);
    assert.doesNotMatch(source, /mode: row\.isInitialized \? 'detail' : 'edit'/);
    assert.match(source, /readOnlyDisabled: !row\.isInitialized/);
    assert.match(source, /views\/fields\/float/);
    assert.match(source, /views\/modals\/balance-correction/);
    assert.match(source, /views\/modals\/correction-history/);
    assert.doesNotMatch(source, /type="number"/);
    assert.match(correctionModal, /HolidayManagement\/correct/);
    assert.match(correctionModal, /views\/fields\/float/);
    assert.match(correctionModal, /views\/fields\/text/);
    assert.match(correctionModal, /idempotencyKey/);
    assert.match(correctionModal, /reason/);
    assert.doesNotMatch(correctionModal, /<input|<textarea/);
    assert.match(correctionHistoryModal, /profiles\/\$\{encodeURIComponent\(this\.profileId\)\}\/corrections/);
    assert.match(correctionHistoryModal, /balanceBefore/);
    assert.match(correctionHistoryModal, /balanceAfter/);
    assert.match(correctionHistoryModal, /actorName/);
    assert.match(correctionHistoryModal, /reason/);
    assert.match(initialSetupModal, /HolidayManagement\/completeInitialSetup/);
    assert.match(initialSetupModal, /openingBalance/);
    assert.match(source, /nextResetDate/);
    assert.match(source, /calendarColor/);
    assert.match(source, /views\/fields\/date/);
    assert.match(source, /views\/fields\/colorpicker/);
    assert.doesNotMatch(source, /type="date"/);
    assert.match(source, /idempotencyKey/);
    assert.match(source, /prop\('disabled'/);
    assert.doesNotMatch(source, /disableButton|enableButton/);
});

test('Administration exposes an admin-only guarded test-data reset', async () => {
    const settings = await readModuleJson('Resources', 'metadata', 'entityDefs', 'Settings.json');
    const resetService = await readModuleSource(
        'Tools', 'HolidayBalance', 'HolidayDataResetService.php'
    );
    const previewAction = await readModuleSource(
        'Tools', 'HolidayBalance', 'Api', 'GetTestDataResetPreview.php'
    );
    const resetAction = await readModuleSource(
        'Tools', 'HolidayBalance', 'Api', 'PostTestDataReset.php'
    );
    const resetField = await readFile(path.join(
        extensionRoot,
        'files', 'client', 'custom', 'modules', 'holiday-management',
        'src', 'views', 'fields', 'test-data-reset.js'
    ), 'utf8');

    assert.equal(settings.fields.holidayManagementTestDataReset.notStorable, true);
    assert.match(previewAction, /user->isAdmin\(\)/);
    assert.match(resetAction, /user->isAdmin\(\)/);
    assert.match(resetAction, /RESET HOLIDAY DATA/);
    assert.match(resetService, /holidayManagementAutomaticAnnualGrantEnabled/);
    assert.match(resetService, /skipHooks/);
    assert.match(resetService, /fileStorageManager->unlink/);
    assert.match(resetService, /'isInitialized'\s*=>\s*false/);
    assert.match(resetService, /balanceWarningNotifications/);
    assert.match(resetService, /'relatedType'\s*=>\s*self::PROFILE/);
    assert.match(resetField, /HolidayManagement\/testDataResetPreview/);
    assert.match(resetField, /HolidayManagement\/testDataReset/);
    assert.match(resetField, /RESET HOLIDAY DATA/);
    assert.match(resetField, /Balance Warning Notifications/);
});

test('accounting metadata remains bilingual in the 1.8.18 package', async () => {
    const manifest = await readJson('manifest.json');

    assert.equal(manifest.version, '1.8.18');

    for (const locale of ['en_US', 'ro_RO']) {
        const admin = await readModuleJson('Resources', 'i18n', locale, 'Admin.json');
        const profile = await readModuleJson('Resources', 'i18n', locale, 'HolidayProfile.json');
        const ledger = await readModuleJson('Resources', 'i18n', locale, 'HolidayLedger.json');
        const global = await readModuleJson('Resources', 'i18n', locale, 'Global.json');
        const request = await readModuleJson('Resources', 'i18n', locale, 'HolidayRequest.json');

        assert.equal(typeof admin.labels['Holiday Profiles'], 'string');
        assert.equal(typeof admin.labels['Correction History'], 'string');
        assert.equal(typeof admin.messages['No Corrections'], 'string');
        assert.equal(typeof admin.messages.correctionHistoryLoadFailed, 'string');
        assert.equal(typeof admin.descriptions.holidayManagementProfiles, 'string');
        assert.equal(typeof request.messages.balanceWarningEmployeeNotification, 'string');
        assert.equal(typeof request.messages.balanceWarningApproverNotification, 'string');
        assert.equal(typeof global.scopeNames.HolidayProfileManagement, 'string');
        assert.equal(typeof global.scopeNamesPlural.HolidayProfileManagement, 'string');
        assert.equal(typeof global.scopeNames.HolidayRequestManagement, 'string');
        assert.equal(typeof global.scopeNamesPlural.HolidayRequestManagement, 'string');
        assert.equal(typeof global.scopeNames.HolidayBalanceCorrection, 'string');
        assert.equal(typeof global.scopeNamesPlural.HolidayBalanceCorrection, 'string');

        for (const field of [
            'user', 'annualEntitlement', 'balance', 'nextResetDate', 'calendarColor',
            'isInitialized', 'resetPending',
        ]) {
            assert.equal(typeof profile.fields[field], 'string', `${locale} missing profile ${field}`);
        }

        for (const field of [
            'profile', 'type', 'delta', 'balanceBefore', 'balanceAfter',
            'actor', 'reason', 'effectiveDate', 'idempotencyKey',
        ]) {
            assert.equal(typeof ledger.fields[field], 'string', `${locale} missing ledger ${field}`);
        }
    }
});

test('Docker harness covers PHASE-001 upgrade, accounting, idempotency, and concurrency', async () => {
    const source = await readFile(path.join(import.meta.dirname, 'docker.ps1'), 'utf8');

    assert.match(source, /git archive/);
    assert.ok((source.match(/bin\/command extension/g) ?? []).length >= 2);
    assert.match(source, /HolidayManagement\/bulkInitialize/);
    assert.match(source, /HolidayManagement\/correct/);
    assert.match(source, /HolidayManagement\/reset/);
    assert.match(source, /idempotencyKey/);
    assert.match(source, /Start-Process/);
    assert.match(source, /101\.0/);
    assert.match(source, /warning threshold/i);
    assert.match(source, /resetPending/);
    assert.match(source, /HolidayLedger/);
});
