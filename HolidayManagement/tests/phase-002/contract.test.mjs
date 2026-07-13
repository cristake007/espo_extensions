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

test('PHASE-002 does not add request, holiday-sync, approval, calendar, notice, or document entities', async () => {
    const entityFiles = await readdir(path.join(moduleRoot, 'Resources', 'metadata', 'entityDefs'));
    const forbidden = [
        'CompanyHoliday.json',
        'HolidayRequest.json',
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
        (source.match(/findLedgerByKey\(/g) ?? []).length >= 8,
        'idempotency must be rechecked after acquiring mutation locks'
    );
    assert.match(source, /idempotencyKey/);
    assert.match(source, /User::TYPE_REGULAR/);
    assert.match(source, /User::TYPE_ADMIN/);
    assert.match(source, /'isActive'\s*=>\s*true/);
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
    assert.match(source, /Correction reason is required/);
});

test('pending resets use balance plus entitlement eligibility and reasoned override', async () => {
    const source = await readModuleSource(
        'Tools', 'HolidayBalance', 'HolidayBalanceService.php'
    );

    assert.match(source, /BalanceMath::canApplyReset\(/);
    assert.match(source, /holidayManagementResetCeilingDays/);
    assert.match(source, /resetPending/);
    assert.match(source, /automaticReset/);
    assert.match(source, /resetOverride/);
    assert.match(source, /Forced reset reason is required/);
    assert.match(source, /pendingResetKey/);
});

test('module exposes only the four admin balance API routes', async () => {
    const routes = await readModuleJson('Resources', 'routes.json');

    assert.deepEqual(routes, [
        {
            route: '/HolidayManagement/profiles',
            method: 'get',
            actionClassName: 'Espo\\Modules\\HolidayManagement\\Tools\\HolidayBalance\\Api\\GetProfiles',
        },
        {
            route: '/HolidayManagement/bulkInitialize',
            method: 'post',
            actionClassName: 'Espo\\Modules\\HolidayManagement\\Tools\\HolidayBalance\\Api\\PostBulkInitialize',
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
    ]);
});

test('balance API actions are thin and explicitly admin-only', async () => {
    const cases = [
        ['GetProfiles', 'listProfiles'],
        ['PostBulkInitialize', 'bulkInitialize'],
        ['PostCorrection', 'correct'],
        ['PostReset', 'reset'],
    ];

    for (const [className, serviceMethod] of cases) {
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
    const item = adminPanel.holidayManagement.itemList.find(
        candidate => candidate.url === '#Admin/holidayManagementProfiles'
    );
    const source = await readFile(path.join(
        extensionRoot,
        'files', 'client', 'custom', 'modules', 'holiday-management',
        'src', 'views', 'admin', 'profiles.js'
    ), 'utf8');

    assert.ok(item, 'missing Holiday Profiles Administration item');
    assert.equal(item.view, 'modules/holiday-management/views/admin/profiles');
    assert.equal('recordView' in item, false);
    assert.match(source, /HolidayManagement\/profiles/);
    assert.match(source, /HolidayManagement\/bulkInitialize/);
    assert.match(source, /annualEntitlement/);
    assert.match(source, /openingBalance/);
    assert.match(source, /nextResetDate/);
    assert.match(source, /idempotencyKey/);
    assert.match(source, /prop\('disabled'/);
    assert.doesNotMatch(source, /disableButton|enableButton/);
});

test('PHASE-002 is a 1.1.0 bilingual package', async () => {
    const manifest = await readJson('manifest.json');

    assert.equal(manifest.version, '1.1.0');

    for (const locale of ['en_US', 'ro_RO']) {
        const admin = await readModuleJson('Resources', 'i18n', locale, 'Admin.json');
        const profile = await readModuleJson('Resources', 'i18n', locale, 'HolidayProfile.json');
        const ledger = await readModuleJson('Resources', 'i18n', locale, 'HolidayLedger.json');

        assert.equal(typeof admin.labels['Holiday Profiles'], 'string');
        assert.equal(typeof admin.descriptions.holidayManagementProfiles, 'string');

        for (const field of [
            'user', 'annualEntitlement', 'balance', 'nextResetDate',
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
    assert.match(source, /80\.0/);
    assert.match(source, /69\.0/);
    assert.match(source, /resetPending/);
    assert.match(source, /HolidayLedger/);
});
