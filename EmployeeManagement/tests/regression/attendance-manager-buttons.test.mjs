import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = fs.readFileSync(new URL(
    '../../files/client/custom/modules/employee-management/src/views/attendance/overview.js',
    import.meta.url,
), 'utf8');

class Collection {
    constructor(items) { this.items = items; }
    get length() { return this.items.length; }
    prop(name, value) { this.items.forEach(item => item.prop(name, value)); return this; }
    toggleClass(name, enabled) { this.items.forEach(item => item.toggleClass(name, enabled)); return this; }
    attr(name, value) {
        if (value === undefined) { return this.items[0]?.attr(name); }
        this.items.forEach(item => item.attr(name, value));
        return this;
    }
    each(callback) { this.items.forEach((item, index) => callback(index, item)); return this; }
}

class Element {
    constructor(tag) {
        this.tag = tag;
        this.attributes = {};
        this.properties = {};
        this.classes = new Set();
        this.children = [];
        this.parent = null;
        this.textValue = '';
    }
    addClass(value) { String(value).split(/\s+/).filter(Boolean).forEach(item => this.classes.add(item)); return this; }
    removeClass(value) { String(value).split(/\s+/).filter(Boolean).forEach(item => this.classes.delete(item)); return this; }
    toggleClass(value, enabled) { enabled ? this.classes.add(value) : this.classes.delete(value); return this; }
    attr(name, value) {
        if (typeof name === 'object') { Object.assign(this.attributes, name); return this; }
        if (value === undefined) { return this.attributes[name]; }
        this.attributes[name] = String(value);
        return this;
    }
    prop(name, value) { this.properties[name] = value; return this; }
    text(value) { this.textValue = String(value); return this; }
    append(...items) {
        items.flat().filter(Boolean).forEach(item => {
            item.parent = this;
            this.children.push(item);
        });
        return this;
    }
    closest(selector) {
        let item = this;
        while (item && item.tag !== selector) { item = item.parent; }
        return item;
    }
    find(selector) {
        const match = selector.match(/^\[([^=]+)="([^"]+)"\]$/);
        const items = [];
        const visit = item => {
            for (const child of item.children) {
                if (match && child.attributes[match[1]] === match[2]) { items.push(child); }
                visit(child);
            }
        };
        visit(this);
        return new Collection(items);
    }
}

function $(value) {
    if (value instanceof Element) { return value; }
    const match = String(value).match(/^<([a-z0-9]+)>$/i);
    if (!match) { throw new Error(`Unsupported selector in test: ${value}`); }
    return new Element(match[1]);
}

let postHandler = async () => ({userId: 'user-1', date: '2026-10-20', status: 'AtWork', source: 'Manager', revision: 1});
let successCount = 0;
let errorCount = 0;
let AttendanceOverviewView;

class BaseView {}

vm.runInNewContext(source, {
    define(dependencies, factory) { AttendanceOverviewView = factory(BaseView); },
    $,
    Espo: {
        Ajax: {
            getRequest: async () => ({}),
            postRequest: (url, payload) => postHandler(url, payload),
        },
        Ui: {
            success() { successCount++; },
            error() { errorCount++; },
        },
    },
    document: {},
    URL,
    Blob,
    Uint8Array,
    atob,
    Intl,
    Date,
    encodeURIComponent,
});

function view(overrides = {}) {
    return Object.assign(new AttendanceOverviewView(), {
        overview: {users: [], rows: [], missingUsers: [], missingCount: 0, signedCount: 0},
        managerMarkPending: new Set(),
        translate(value) { return value === 'Medical Leave Help' ? 'Full MedicalLeave' : value; },
        getLanguage() { return {translateOption(value) { return `Full ${value}`; }}; },
        renderOverview() {},
    }, overrides);
}

function editableCell(status = '', cicActive = false) {
    return {
        userId: 'user-1',
        date: '2026-10-20',
        status,
        source: status ? 'Manager' : null,
        revision: status ? 3 : 0,
        isWorkingDay: true,
        isFuture: false,
        canManage: true,
        cicActive,
    };
}

function buttons(cell) {
    return cell.find('[data-action="manager-mark-attendance"]').items;
}

function deferred() {
    let resolve;
    let reject;
    const promise = new Promise((resolvePromise, rejectPromise) => {
        resolve = resolvePromise;
        reject = rejectPromise;
    });
    return {promise, resolve, reject};
}

test('editable cells render compact P, D and CM buttons with full accessible labels', () => {
    const cell = view().createOverviewCell(editableCell('BusinessTrip'));
    const rendered = buttons(cell);

    assert.deepEqual(rendered.map(item => item.textValue), ['P', 'D', 'CM']);
    assert.deepEqual(rendered.map(item => item.attr('title')), [
        'Full AtWork',
        'Full BusinessTrip',
        'Full MedicalLeave',
    ]);
    assert.deepEqual(rendered.map(item => item.attr('aria-label')), rendered.map(item => item.attr('title')));
    assert.deepEqual(rendered.map(item => item.attr('aria-pressed')), ['false', 'true', 'false']);
    assert.equal(rendered[1].classes.has('active'), true);
});

test('unmarked cells have no active button and CIC cells expose only the permitted CM action', () => {
    assert.equal(buttons(view().createOverviewCell(editableCell())).some(item => item.classes.has('active')), false);
    const cicButtons = buttons(view().createOverviewCell(editableCell('ChildCareLeave', true)));
    assert.deepEqual(cicButtons.map(item => item.textValue), ['CM']);
    assert.equal(cicButtons[0].attr('data-status'), 'MedicalLeave');
});

test('one click saves through the routine endpoint without a reason or correction modal', async () => {
    let request;
    const instance = view({
        createView() { throw new Error('Correction modal must not be opened.'); },
        updateOverviewCell() {},
    });
    const cell = instance.createOverviewCell(editableCell());
    const button = buttons(cell)[0];
    postHandler = async (url, payload) => {
        request = {url, payload};
        return {userId: 'user-1', date: '2026-10-20', status: 'AtWork', source: 'Manager', revision: 1};
    };

    await instance.actionManagerMarkAttendance({currentTarget: button});

    assert.equal(request.url, 'EmployeeManagement/attendance/managerMarkRoutine');
    assert.deepEqual(Object.keys(request.payload).sort(), ['date', 'revision', 'status', 'userId']);
    assert.equal('reason' in request.payload, false);
});

test('clicking the current status does not issue a request', async () => {
    let writes = 0;
    const instance = view();
    const cell = instance.createOverviewCell(editableCell('AtWork'));
    postHandler = async () => { writes++; return {}; };
    await instance.actionManagerMarkAttendance({currentTarget: buttons(cell)[0]});
    assert.equal(writes, 0);
});

test('a failed request restores the previous status and reports one error', async () => {
    const statuses = [];
    const pending = [];
    const instance = view({
        setManagerCellStatus(cell, status) { statuses.push(status); cell.attr('data-status', status || 'Missing'); },
        setManagerCellPending(cell, value) { pending.push(value); },
    });
    const cell = instance.createOverviewCell(editableCell('AtWork'));
    postHandler = async () => { throw new Error('temporary failure'); };
    errorCount = 0;

    await instance.actionManagerMarkAttendance({currentTarget: buttons(cell)[1]});

    assert.deepEqual(statuses, ['BusinessTrip', 'AtWork']);
    assert.deepEqual(pending, [true, false]);
    assert.equal(errorCount, 1);
    assert.equal(instance.managerMarkPending.size, 0);
});

test('rapid clicks for the same employee/day produce only one in-flight write', async () => {
    const request = deferred();
    let writes = 0;
    const instance = view({updateOverviewCell() {}});
    const cell = instance.createOverviewCell(editableCell());
    const rendered = buttons(cell);
    postHandler = () => { writes++; return request.promise; };

    const first = instance.actionManagerMarkAttendance({currentTarget: rendered[0]});
    const second = instance.actionManagerMarkAttendance({currentTarget: rendered[1]});
    assert.equal(writes, 1);
    request.resolve({userId: 'user-1', date: '2026-10-20', status: 'AtWork', source: 'Manager', revision: 1});
    await Promise.all([first, second]);
    assert.equal(instance.managerMarkPending.size, 0);
});

test('switching allowed statuses updates the stored cell revision without changing audit source semantics', () => {
    const modelCell = editableCell('AtWork');
    const instance = view({
        overview: {users: [], rows: [{cells: [modelCell]}], missingUsers: [], missingCount: 0, signedCount: 1},
    });
    instance.updateOverviewCell({
        userId: 'user-1',
        date: '2026-10-20',
        status: 'MedicalLeave',
        source: 'Manager',
        revision: 4,
    });
    assert.equal(modelCell.status, 'MedicalLeave');
    assert.equal(modelCell.source, 'Manager');
    assert.equal(modelCell.revision, 4);
});
