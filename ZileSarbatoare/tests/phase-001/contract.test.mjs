import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';

const extensionRoot = path.resolve(import.meta.dirname, '..', '..');
const moduleRoot = path.join(
    extensionRoot,
    'files', 'custom', 'Espo', 'Modules', 'ZileSarbatoare'
);

async function readJson(...segments) {
    return JSON.parse(await readFile(path.join(moduleRoot, ...segments), 'utf8'));
}

async function readSource(...segments) {
    return readFile(path.join(moduleRoot, ...segments), 'utf8');
}

test('ZileLibere stores one canonical date and the required synchronization scope indexes', async () => {
    const defs = await readJson('Resources', 'metadata', 'entityDefs', 'ZileLibere.json');

    assert.equal(defs.fields.dateStart.type, 'date');
    assert.equal(defs.fields.dateStart.required, true);
    assert.equal(defs.fields.dateEnd, undefined);
    assert.equal(defs.fields.holidayTypes.type, 'array');
    assert.equal(defs.fields.subdivisionCodes.type, 'array');
    assert.deepEqual(defs.fields.subdivisionCodes.default, []);
    assert.equal(defs.fields.subdivisionCodes.required, undefined);
    assert.equal(defs.fields.year.notStorable, true);
    assert.equal(defs.fields.month.notStorable, true);
    assert.deepEqual(defs.indexes.dateStart.columns, ['dateStart', 'deleted']);
    assert.deepEqual(defs.indexes.countryDate.columns, ['countryCode', 'dateStart', 'deleted']);
    assert.deepEqual(
        defs.indexes.managedSyncScope.columns,
        ['source', 'managed', 'countryCode', 'sourceYear', 'deleted']
    );
});

test('navigation and Quick Create use the holiday icon and compact manual layout', async () => {
    const clientDefs = await readJson('Resources', 'metadata', 'clientDefs', 'ZileLibere.json');
    const detailSmall = await readJson('Resources', 'layouts', 'ZileLibere', 'detailSmall.json');
    const fieldNames = detailSmall[0].rows.flat().filter(Boolean).map(item => item.name);

    assert.equal(clientDefs.iconClass, 'fas fa-calendar-day');
    assert.deepEqual(fieldNames, ['name', 'dateStart', 'countryCode', 'description']);
});

test('scope is a global one-day calendar entity with read-only role mutation levels', async () => {
    const scope = await readJson('Resources', 'metadata', 'scopes', 'ZileLibere.json');
    const entity = await readSource('Entities', 'ZileLibere.php');

    assert.equal(scope.type, 'Event');
    assert.equal(scope.calendar, true);
    assert.equal(scope.calendarOneDay, true);
    assert.deepEqual(scope.aclActionLevelListMap.read, ['all', 'no']);
    assert.deepEqual(scope.aclActionLevelListMap.create, ['no']);
    assert.deepEqual(scope.aclActionLevelListMap.edit, ['no']);
    assert.deepEqual(scope.aclActionLevelListMap.delete, ['no']);
    assert.equal(scope.importable, false);
    assert.match(entity, /function getAssignedUser\(\): \?Link/);
    assert.match(entity, /getAssignedUser\(\): \?Link\s*\{\s*return null;/s);
});

test('manual record hooks own defaults and reject managed update and deletion paths', async () => {
    const recordDefs = await readJson('Resources', 'metadata', 'recordDefs', 'ZileLibere.json');
    const policy = await readSource('Tools', 'ZileLibere', 'ManualRecordPolicy.php');
    const accessChecker = await readSource('Classes', 'Acl', 'ZileLibere', 'AccessChecker.php');
    const update = await readSource('Classes', 'Record', 'Hooks', 'ZileLibere', 'BeforeUpdate.php');
    const remove = await readSource('Classes', 'Record', 'Hooks', 'ZileLibere', 'BeforeDelete.php');

    assert.equal(recordDefs.earlyBeforeCreateHookClassNameList.length, 1);
    assert.equal(recordDefs.earlyBeforeUpdateHookClassNameList.length, 1);
    assert.equal(recordDefs.beforeDeleteHookClassNameList.length, 1);
    assert.equal(recordDefs.massActions.update.disabled, true);
    assert.equal(recordDefs.massActions.delete.disabled, true);

    for (const value of [
        "'source', ZileLibere::SOURCE_MANUAL",
        "'managed', false",
        "'sourceYear', null",
        "'syncedAt', null",
        "'holidayTypes', ['Public']",
        "'nationalHoliday', true",
        "'subdivisionCodes', []",
    ]) {
        assert.ok(policy.includes(value), `manual policy is missing ${value}`);
    }

    assert.match(update, /getFetched\('managed'\)/);
    assert.match(update, /SOURCE_NAGER_DATE/);
    assert.match(remove, /Synchronized Zile libere records cannot be deleted/);
    assert.match(accessChecker, /function checkCreate[\s\S]*?return \$user->isAdmin\(\);/);
    assert.match(accessChecker, /function checkEntityCreate[\s\S]*?return \$user->isAdmin\(\);/);
});

test('calendar query preserves strict ACL and does not filter by assigned user', async () => {
    const source = await readSource('Services', 'ZileLibere.php');

    assert.match(source, /function getCalenderQuery\(/);
    assert.match(source, /withStrictAccessControl\(\)/);
    assert.match(source, /\['dateStart', 'dateStartDate'\]/);
    assert.match(source, /\['dateStart', 'dateEndDate'\]/);
    assert.doesNotMatch(source, /assignedUserId|leftJoin\('users'\)/);
});

test('calendar registration is additive and idempotent', async () => {
    const source = await readFile(path.join(extensionRoot, 'scripts', 'AfterInstall.php'), 'utf8');

    assert.match(source, /\['calendarEntityList', 'tabList', 'quickCreateList'\]/);
    assert.match(source, /in_array\(self::ENTITY_TYPE, \$entityTypeList, true\)/);
    assert.match(source, /\$entityTypeList\[\] = self::ENTITY_TYPE/);
    assert.doesNotMatch(source, /\['ZileLibere'\]/);
});
