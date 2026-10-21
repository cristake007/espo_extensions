import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const root = new URL('../../', import.meta.url);
const read = path => fs.readFileSync(new URL(path, root), 'utf8');
const personalSource = read('files/client/custom/modules/employee-management/src/views/holiday-request/index.js');
const managementSource = read('files/client/custom/modules/employee-management/src/views/holiday-management/index.js');
const controllerSource = read('files/client/custom/modules/employee-management/src/controllers/workspace.js');

class PersonalView {
    data() { return {rows:this.rows || [], hasRows:(this.rows || []).length > 0, rowCount:(this.rows || []).length}; }
    translate(value) { return value; }
}

let ManagementView;
vm.runInNewContext(managementSource, {
    define(dependencies, factory) { ManagementView = factory(PersonalView); },
    Espo:{Ui:{success() {}}},
});

test('personal workspace contains no manager controls or manager navigation', () => {
    for (const forbidden of [
        'data-name="manager-actions"',
        'data-action="managed"',
        'data-action="queue"',
        'data-action="approved"',
        '#EmployeeHolidayDocumentVerification',
    ]) {
        assert.equal(personalSource.includes(forbidden), false, forbidden);
    }
    assert.match(personalSource, /managerList && canQueue && row\.status === 'Pending'/);
    assert.match(personalSource, /managerList && canQueue && row\.status === 'Approved'/);
    assert.match(personalSource, /row\.assignedUserId === this\.getUser\(\)\.id/);
});

test('manager queue is split locally without adding an API', () => {
    const rows = [
        {id:'pending', status:'Pending'},
        {id:'processing', status:'ApprovalProcessing'},
        {id:'cancellation', status:'CancellationPending'},
    ];
    const view = new ManagementView();
    view.section = 'approvals';
    assert.deepEqual(
        Array.from(view.filterResponseRows(rows), row => row.id),
        ['pending', 'processing'],
    );
    view.section = 'cancellations';
    assert.deepEqual(Array.from(view.filterResponseRows(rows), row => row.id), ['cancellation']);
    assert.match(managementSource, /initialListMode = this\.section === 'approved' \? 'approved' : 'queue'/);
    assert.doesNotMatch(managementSource, /EmployeeManagement\/holiday\/(approvals|cancellations)/);
});

test('all authorized manager options remain in one persistent native view', () => {
    for (const route of [
        '#EmployeeHolidayManagement/approvals',
        '#EmployeeHolidayManagement/cancellations',
        '#EmployeeHolidayManagement/approved',
        '#EmployeeHolidayManagement/book',
        '#EmployeeHolidayDocumentVerification',
    ]) {
        assert.ok(managementSource.includes(route), route);
    }
    assert.match(managementSource, /employee-holiday-management-option\{\{#if approvalsActive\}\} is-active/);
    assert.match(managementSource, /employee-holiday-management-option\{\{#if cancellationsActive\}\} is-active/);
    assert.match(managementSource, /employee-holiday-management-option\{\{#if approvedActive\}\} is-active/);
});

test('route authorization preserves independent booking and approval permissions', () => {
    assert.match(controllerSource, /checkScope\('EmployeeHolidayBooking'\)/);
    assert.match(controllerSource, /checkScope\('EmployeeHolidayRequestManagement'\)/);
    assert.match(controllerSource, /getLevel\('EmployeeHolidayProfile', 'read'\) === 'all'/);
    assert.match(controllerSource, /checkScope\('EmployeeHolidayApproval'\)/);
    assert.match(controllerSource, /getLevel\('EmployeeHolidayRequest', 'read'\) !== 'all'/);
    assert.match(controllerSource, /!canManagedBooking && !canApprove/);
    assert.match(controllerSource, /approvalSections\.includes\(section\) && !canApprove/);
    assert.match(controllerSource, /section === 'book' && !canManagedBooking/);
});

test('native tab metadata supplies the manager icon without altering user navigation', () => {
    const scope = JSON.parse(read('files/custom/Espo/Modules/EmployeeManagement/Resources/metadata/scopes/EmployeeHolidayManagement.json'));
    const client = JSON.parse(read('files/custom/Espo/Modules/EmployeeManagement/Resources/metadata/clientDefs/EmployeeHolidayManagement.json'));
    const navbar = JSON.parse(read('files/custom/Espo/Modules/EmployeeManagement/Resources/metadata/app/clientNavbar.json'));
    assert.equal(scope.tab, true);
    assert.equal(scope.acl, 'boolean');
    assert.equal(client.iconClass, 'fas fa-user-shield');
    assert.deepEqual(navbar, {});
});
