import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import {fileURLToPath} from 'node:url';

const testDirectory = path.dirname(fileURLToPath(import.meta.url));
const extensionRoot = path.resolve(testDirectory, '../..');
const viewPath = path.join(
    extensionRoot,
    'files/client/custom/modules/generator-perioade-cursuri/src/views/fields/holidays.js'
);
const localeRoot = path.join(
    extensionRoot,
    'files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/i18n'
);

const translations = Object.fromEntries(['en_US', 'ro_RO'].map(locale => [
    locale,
    JSON.parse(fs.readFileSync(
        path.join(localeRoot, locale, 'GeneratorPerioadeCursuri.json'),
        'utf8'
    )),
]));

class FakeInput {
    constructor(value = '') {
        this.value = value;
        this.focusCount = 0;
    }

    focus() {
        this.focusCount++;
    }
}

class FakeRow {
    constructor(container, value = '') {
        this.container = container;
        this.input = new FakeInput(value);
        this.dataset = {};
        this.style = {};
        this.className = '';
    }

    set innerHTML(value) {
        this.markup = value;
    }

    querySelector(selector) {
        return selector === 'input.holiday-date' ? this.input : null;
    }

    closest(selector) {
        return selector === '[data-role="date-row"]' ? this : null;
    }

    remove() {
        this.container.rows = this.container.rows.filter(row => row !== this);
    }
}

class FakeContainer {
    constructor(values) {
        this.rows = values.map(value => new FakeRow(this, value));
    }

    appendChild(row) {
        row.container = this;
        this.rows.push(row);
    }

    querySelector(selector) {
        return selector === '[data-role="date-row"]' ? (this.rows[0] ?? null) : null;
    }
}

class FakeRoot {
    constructor(values) {
        this.container = new FakeContainer(values);
        this.hidden = new FakeInput();
        this.importButton = {disabled: false};
    }

    querySelector(selector) {
        if (selector === '[data-role="date-list"]') {
            return this.container;
        }

        if (selector === 'input.main-element') {
            return this.hidden;
        }

        if (selector === '[data-action="importHolidayDates"]') {
            return this.importButton;
        }

        return null;
    }

    querySelectorAll(selector) {
        if (selector === 'input.holiday-date') {
            return this.container.rows.map(row => row.input);
        }

        if (selector === '[data-role="date-row"]') {
            return this.container.rows.slice();
        }

        return [];
    }
}

class FakeDatepicker {
    constructor(input, options) {
        this.input = input;
        this.options = options;
        this.showCount = 0;
    }

    show() {
        this.showCount++;
    }
}

class VarcharFieldView {
    setup() {}
    afterRender() {}

    addHandler(event, selector, callback) {
        this.handlers ??= new Map();
        this.handlers.set(`${event}:${selector}`, callback);
    }

    data() {
        return {};
    }

    isEditMode() {
        return true;
    }

    getDateTime() {
        return {
            dateFormat: 'DD.MM.YYYY',
            weekStart: 1,
            toDisplayDate(value) {
                const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
                return match ? `${match[3]}.${match[2]}.${match[1]}` : value;
            },
            fromDisplayDate(value) {
                const match = /^(\d{2})\.(\d{2})\.(\d{4})$/.exec(value);
                return match ? `${match[3]}-${match[2]}-${match[1]}` : value;
            },
        };
    }

    getConfig() {
        return {get() { return false; }};
    }

    trigger(event) {
        this.triggeredEvents ??= [];
        this.triggeredEvents.push(event);
    }

    showValidationMessage(message, selector) {
        this.validationMessages ??= [];
        this.validationMessages.push([message, selector]);
    }
}

const notifications = [];
let ajaxImplementation = async () => ({dates: []});
const Espo = {
    Ajax: {
        postRequest(url, payload) {
            return ajaxImplementation(url, payload);
        },
    },
    Ui: {
        notify(...args) {
            notifications.push(args);
        },
    },
};

let HolidaysView;
vm.runInNewContext(fs.readFileSync(viewPath, 'utf8'), {
    console,
    document: {
        createElement(tagName) {
            assert.equal(tagName, 'div');
            return new FakeRow(null);
        },
    },
    Espo,
    HTMLInputElement: FakeInput,
    define(name, dependencies, factory) {
        assert.equal(name, 'generator-perioade-cursuri:views/fields/holidays');
        assert.deepEqual(Array.from(dependencies), [
            'views/fields/varchar',
            'ui/datepicker',
            'generator-perioade-cursuri:views/shared/record-ui',
        ]);
        HolidaysView = factory(
            VarcharFieldView,
            FakeDatepicker,
            {escapeHtml: value => String(value)}
        );
    },
}, {filename: viewPath});

assert.equal(typeof HolidaysView, 'function', 'the production holiday field must load through AMD');

function createView({
    locale = 'ro_RO',
    year = 2026,
    months = ['1'],
    values = [''],
    storedValue = null,
    ajax = async () => ({dates: []}),
} = {}) {
    notifications.length = 0;
    ajaxImplementation = ajax;

    const modelValues = {
        holidays: storedValue,
        year,
        selectedMonths: months,
    };
    const view = new HolidaysView();
    view.name = 'holidays';
    view.model = {
        get(name) {
            return modelValues[name];
        },
    };
    view.element = new FakeRoot(values);
    view.translate = (key, category) =>
        translations[locale][category]?.[key] ?? key;
    view.setup();
    view.afterRender();

    return {view, modelValues};
}

function getImportHandler(view) {
    const selector = '[data-action="importHolidayDates"]';
    const handler = view.handlers?.get(`click:${selector}`);

    assert.equal(
        typeof handler,
        'function',
        'edit mode must register the explicit holiday-import button handler'
    );

    return handler;
}

async function clickImport(view) {
    return getImportHandler(view)({}, view.element.importButton);
}

function inputValues(view) {
    return view.element.querySelectorAll('input.holiday-date').map(input => input.value);
}

function lastNotification() {
    return notifications.at(-1)?.[0];
}

function plain(value) {
    return JSON.parse(JSON.stringify(value));
}

{
    let requests = 0;
    const {view, modelValues} = createView({
        storedValue: '05.01.2026',
        values: ['05.01.2026'],
        ajax: async () => {
            requests++;
            return {dates: []};
        },
    });
    const data = view.data();

    assert.match(view.editTemplateContent, /data-action="importHolidayDates"/);
    assert.equal(typeof data.importLabel, 'string');
    assert.notEqual(data.importLabel, 'Import holiday dates');
    assert.equal(requests, 0, 'setup and render must not import holidays');

    modelValues.year = 2027;
    modelValues.selectedMonths = ['2', '3'];
    assert.equal(requests, 0, 'year and month changes must not import holidays');
    assert.deepEqual(inputValues(view), ['05.01.2026']);
}

for (const testCase of [
    {year: null, months: ['1'], messageKey: 'holidayImportMissingYear'},
    {year: 2026, months: [], messageKey: 'holidayImportMissingMonths'},
]) {
    let requests = 0;
    const {view} = createView({
        year: testCase.year,
        months: testCase.months,
        ajax: async () => {
            requests++;
            return {dates: []};
        },
    });

    await clickImport(view);
    assert.equal(requests, 0, 'missing selections must stop before Ajax');
    assert.equal(
        lastNotification(),
        translations.ro_RO.messages[testCase.messageKey],
        `missing selection must use ${testCase.messageKey}`
    );
}

{
    let releaseRequest;
    const pending = new Promise(resolve => { releaseRequest = resolve; });
    const calls = [];
    const {view} = createView({
        months: ['2', '10'],
        values: ['05.01.2026', ''],
        ajax: (url, payload) => {
            calls.push([url, payload]);
            return pending;
        },
    });

    const firstClick = clickImport(view);
    const secondClick = clickImport(view);
    assert.equal(calls.length, 1, 'concurrent import clicks must share one request');
    assert.deepEqual(plain(calls[0]), [
        'ZileLibere/availableDates',
        {year: 2026, months: [2, 10]},
    ]);
    assert.equal(view.element.importButton.disabled, true);

    releaseRequest({
        dates: ['2026-01-01', '2026-01-05', '2026-01-01'],
    });
    await Promise.all([firstClick, secondClick]);

    assert.deepEqual(
        inputValues(view),
        ['05.01.2026', '01.01.2026'],
        'manual dates must remain and placeholder blank rows must be removed after import'
    );
    assert.equal(view.element.hidden.value, '05.01.2026, 01.01.2026');
    assert.ok(view.triggeredEvents.includes('change'));
    assert.equal(view.element.importButton.disabled, false);

    await clickImport(view);
    assert.deepEqual(
        inputValues(view),
        ['05.01.2026', '01.01.2026'],
        'repeated imports must not add duplicates'
    );
}

{
    const {view} = createView({ajax: async () => ({dates: []})});
    await clickImport(view);
    assert.equal(
        lastNotification(),
        'Nu există zile libere disponibile pentru anul și lunile selectate. ' +
            'Verifică dacă anul a fost sincronizat în extensia Zile Sărbătoare.'
    );
}

for (const testCase of [
    {error: {status: 400}, messageKey: 'holidayImportBadRequest'},
    {error: {status: 403}, messageKey: 'holidayImportForbidden'},
    {error: {status: 404}, messageKey: 'holidayImportUnavailable'},
    {error: {status: 405}, messageKey: 'holidayImportUnavailable'},
    {error: {status: 0}, messageKey: 'holidayImportTemporaryUnavailable'},
    {error: {status: 503}, messageKey: 'holidayImportTemporaryUnavailable'},
    {error: new Error('internal stack details'), messageKey: 'holidayImportTemporaryUnavailable'},
]) {
    const {view} = createView({
        ajax: async () => { throw testCase.error; },
    });

    await clickImport(view);
    assert.equal(lastNotification(), translations.ro_RO.messages[testCase.messageKey]);
    assert.doesNotMatch(String(lastNotification()), /stack|details|undefined|\[object Object\]/i);
    assert.equal(view.element.importButton.disabled, false);
    assert.deepEqual(inputValues(view), ['']);
}

assert.match(
    translations.ro_RO.messages.holidayImportUnavailable,
    /extensia Zile Sărbătoare.*instalată.*activată/i
);

for (const response of [
    null,
    {},
    {dates: '2026-01-01'},
    {dates: ['2026-1-01']},
    {dates: ['2026-02-30']},
    {dates: ['not-a-date']},
]) {
    const {view} = createView({ajax: async () => response});

    await clickImport(view);
    assert.equal(lastNotification(), translations.ro_RO.messages.holidayImportInvalidResponse);
    assert.deepEqual(inputValues(view), ['']);
}

{
    const {view} = createView({
        values: ['03.01.2026'],
        ajax: async () => { throw {status: 404}; },
    });

    await clickImport(view);
    view.addHolidayDate();
    const values = inputValues(view);
    values[1] = '04.01.2026';
    view.element.querySelectorAll('input.holiday-date')[1].value = values[1];
    view.handleDateInput();

    assert.deepEqual(plain(view.fetch()), {holidays: '03.01.2026, 04.01.2026'});
    assert.equal(view.validateHolidayDates(), false);

    const firstRow = view.element.container.rows[0];
    view.removeHolidayDate(firstRow);
    assert.deepEqual(inputValues(view), ['04.01.2026']);
}

console.log('Holiday import offline contracts passed; production AMD field executed.');
