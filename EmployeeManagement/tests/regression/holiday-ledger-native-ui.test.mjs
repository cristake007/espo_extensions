import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const root = new URL('../../', import.meta.url);
const read = path => fs.readFileSync(new URL(path, root), 'utf8');
const json = path => JSON.parse(read(path));

const entity = json('files/custom/Espo/Modules/EmployeeManagement/Resources/metadata/entityDefs/EmployeeHolidayLedger.json');
const list = json('files/custom/Espo/Modules/EmployeeManagement/Resources/layouts/EmployeeHolidayLedger/list.json');
const listSmall = json('files/custom/Espo/Modules/EmployeeManagement/Resources/layouts/EmployeeHolidayLedger/listSmall.json');
const detail = json('files/custom/Espo/Modules/EmployeeManagement/Resources/layouts/EmployeeHolidayLedger/detail.json');
const filters = json('files/custom/Espo/Modules/EmployeeManagement/Resources/layouts/EmployeeHolidayLedger/filters.json');
const client = json('files/custom/Espo/Modules/EmployeeManagement/Resources/metadata/clientDefs/EmployeeHolidayLedger.json');
const record = json('files/custom/Espo/Modules/EmployeeManagement/Resources/metadata/recordDefs/EmployeeHolidayLedger.json');
const scope = json('files/custom/Espo/Modules/EmployeeManagement/Resources/metadata/scopes/EmployeeHolidayLedger.json');
const acl = json('files/custom/Espo/Modules/EmployeeManagement/Resources/metadata/aclDefs/EmployeeHolidayLedger.json');
const entityAcl = json('files/custom/Espo/Modules/EmployeeManagement/Resources/metadata/entityAcl/EmployeeHolidayLedger.json');
const ro = json('files/custom/Espo/Modules/EmployeeManagement/Resources/i18n/ro_RO/EmployeeHolidayLedger.json');
const en = json('files/custom/Espo/Modules/EmployeeManagement/Resources/i18n/en_US/EmployeeHolidayLedger.json');
const appClient = json('files/custom/Espo/Modules/EmployeeManagement/Resources/metadata/app/client.json');
const pageView = read('files/client/custom/modules/employee-management/src/views/holiday-ledger/list.js');
const recordListView = read('files/client/custom/modules/employee-management/src/views/holiday-ledger/record/list.js');
const ledgerCss = read('files/client/custom/modules/employee-management/css/holiday-ledger.css');

assert.deepEqual(list.map(item => item.name), [
    'createdAt', 'type', 'assignedUser', 'balanceBefore', 'delta', 'balanceAfter', 'actor',
]);
assert.deepEqual(listSmall.map(item => item.name), ['createdAt', 'type', 'delta', 'balanceAfter']);
assert.deepEqual(entity.collection, {orderBy: 'createdAt', order: 'desc'});
assert.deepEqual(filters, ['assignedUser', 'type', 'createdAt', 'effectiveDate', 'actor']);
assert.equal(entity.fields.assignedUser.type, 'link');
assert.equal(entity.links.assignedUser.entity, 'User');
assert.equal(entity.fields.delta.view, 'employee-management:views/fields/signed-holiday-days');
assert.equal(list[0].link, true);
assert.equal(listSmall[0].link, true);

const detailFields = detail.flatMap(panel => panel.rows).flat().filter(Boolean).map(item => item.name);
for (const name of [
    'type', 'assignedUser', 'createdAt', 'balanceBefore', 'delta', 'balanceAfter',
    'actor', 'reason', 'request', 'effectiveDate', 'entitlementBefore', 'entitlementAfter',
    'resetDateBefore', 'resetDateAfter',
]) assert.ok(detailFields.includes(name), `detail field ${name}`);
for (const forbidden of ['idempotencyKey', 'operationHash']) assert.ok(!detailFields.includes(forbidden));

assert.equal(client.controller, 'controllers/record');
assert.equal(client.iconClass, 'fas fa-history');
assert.equal(client.views.list, 'employee-management:views/holiday-ledger/list');
assert.equal(client.recordViews.list, 'employee-management:views/holiday-ledger/record/list');
for (const flag of ['createDisabled', 'editDisabled', 'removeDisabled', 'massUpdateDisabled', 'massRemoveDisabled']) {
    assert.equal(client[flag], true, flag);
}
assert.deepEqual(scope.aclActionLevelListMap.read, ['all', 'own', 'no']);
for (const action of ['create', 'edit', 'delete']) assert.deepEqual(scope.aclActionLevelListMap[action], ['no']);
assert.match(acl.accessCheckerClassName, /ReadOnlyAccessChecker$/);
assert.equal(entityAcl.fields.idempotencyKey.forbidden, true);
assert.equal(entityAcl.fields.operationHash.forbidden, true);
assert.equal(record.earlyBeforeCreateHookClassNameList.length, 1);
assert.equal(record.earlyBeforeUpdateHookClassNameList.length, 1);
assert.equal(record.beforeDeleteHookClassNameList.length, 1);

for (const translations of [ro, en]) {
    for (const option of entity.fields.type.options) assert.ok(translations.options.type[option], `${option} translation`);
    for (const field of list.map(item => item.name)) assert.ok(translations.fields[field], `${field} translation`);
    for (const label of ['Holiday History', 'Filter Employee', 'All Employees', 'My History']) {
        assert.ok(translations.labels[label], `${label} translation`);
    }
    for (const message of ['holidayHistoryIntro', 'managerEmployeeFilterHelp', 'employeeLedgerHelp']) {
        assert.ok(translations.messages[message], `${message} translation`);
    }
}

assert.ok(appClient.cssList.includes('client/custom/modules/employee-management/css/holiday-ledger.css'));
for (const token of [
    "define(['views/list']", 'employee-holiday-ledger-page', "'views/fields/user'",
    'this.searchManager.set(data)', 'this.collection.fetch({main: true})',
    'employee-holiday-ledger-empty', 'employee-holiday-ledger-error',
]) assert.ok(pageView.includes(token), `page view token ${token}`);
assert.ok(!pageView.includes('Espo.Ajax'));
assert.ok(recordListView.includes("define(['views/record/list']"));
for (const token of [
    'max-width: 1180px', '.employee-holiday-ledger-hero',
    '.employee-holiday-ledger-row-positive', '@media (max-width: 767px)',
]) assert.ok(ledgerCss.includes(token), `ledger CSS token ${token}`);

let SignedFieldView;
class FloatFieldView {
    constructor(value, formatted) {
        this.name = 'delta';
        this.model = {get: () => value};
        this.formatted = formatted;
    }
    getValueForDisplay() { return this.formatted; }
}
vm.runInNewContext(read('files/client/custom/modules/employee-management/src/views/fields/signed-holiday-days.js'), {
    define: (_dependencies, factory) => { SignedFieldView = factory(FloatFieldView); },
    Number,
});
assert.equal(new SignedFieldView(5, '5').getValueForDisplay(), '+5');
assert.equal(new SignedFieldView(-3, '-3').getValueForDisplay(), '−3');
assert.equal(new SignedFieldView(0, '0').getValueForDisplay(), '0');
assert.equal(new SignedFieldView(1.25, '1,25').getValueForDisplay(), '+1,25');

console.log('PASS: native Holiday Ledger UI regression (metadata, ACL, layouts, i18n, signed values).');
