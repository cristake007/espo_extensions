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

    closest(selector) {
        return selector === '[data-role="date-row"]' ? this.row ?? null : null;
    }
}

class FakeRow {
    constructor(container, value = '') {
        this.container = container;
        this.input = new FakeInput(value);
        this.input.row = this;
        this.metadata = {innerHTML: ''};
        this.dataset = {};
        this.style = {};
        this.className = '';
    }

    set innerHTML(value) {
        this.markup = value;
    }

    querySelector(selector) {
        if (selector === 'input.holiday-date') {
            return this.input;
        }

        if (selector === '[data-role="holiday-metadata"]') {
            return this.metadata;
        }

        return null;
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

    querySelectorAll(selector) {
        return selector === '[data-role="date-row"]' ? this.rows.slice() : [];
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

    listenTo(target, event, callback) {
        target.on(event, callback);
    }

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

    confirm(options) {
        this.confirmations ??= [];
        this.confirmations.push(options);

        if (typeof this.confirmAction === 'function') {
            return this.confirmAction(options);
        }

        if (this.confirmAction === 'cancel') {
            options.cancelCallback?.();

            return new Promise(() => {});
        }

        return Promise.resolve();
    }
}

class FakeModel {
    constructor(values) {
        this.values = values;
        this.listeners = new Map();
        this.previousValues = {...values};
    }

    get(name) {
        return this.values[name];
    }

    previous(name) {
        return this.previousValues[name];
    }

    on(event, callback) {
        const callbacks = this.listeners.get(event) ?? [];
        callbacks.push(callback);
        this.listeners.set(event, callbacks);
    }

    set(name, value, options = {}) {
        return this.setMultiple({[name]: value}, options);
    }

    setMultiple(attributes, options = {}) {
        const changedNames = Object.keys(attributes)
            .filter(name => this.values[name] !== attributes[name]);

        if (changedNames.length === 0) {
            return this;
        }

        this.previousValues = {...this.values};
        Object.assign(this.values, attributes);

        changedNames.forEach(name => {
            (this.listeners.get(`change:${name}`) ?? [])
                .forEach(callback => callback(this, this.values[name], options));
        });
        (this.listeners.get('change') ?? [])
            .forEach(callback => callback(this, options));

        return this;
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
    storedDetails = [],
    rowDetails = [],
    confirmation = 'accept',
    ajax = async () => ({dates: []}),
} = {}) {
    notifications.length = 0;
    ajaxImplementation = ajax;

    const modelValues = {
        holidays: storedValue,
        holidayDetails: storedDetails,
        year,
        selectedMonths: months,
    };
    const view = new HolidaysView();
    view.name = 'holidays';
    const model = new FakeModel(modelValues);
    view.model = model;
    view.confirmAction = confirmation;
    view.element = new FakeRoot(values);
    view.translate = (key, category) =>
        translations[locale][category]?.[key] ?? key;
    view.setup();
    view.afterRender();
    rowDetails.forEach((detail, index) => {
        const row = view.element.container.rows[index];

        if (row) {
            view.setHolidayRowMetadata(row, detail);
        }
    });

    return {view, model, modelValues};
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

function flushMicrotasks() {
    return new Promise(resolve => setImmediate(resolve));
}

{
    let requests = 0;
    const importedDetail = {
        date: '2026-01-05',
        name: 'Ajunul Bobotezei',
        type: 'legal',
        source: 'zile-sarbatoare',
    };
    const {view, model} = createView({
        year: undefined,
        storedValue: '05.01.2026',
        storedDetails: [importedDetail],
        values: ['05.01.2026'],
        rowDetails: [importedDetail],
        ajax: async () => {
            requests++;
            return {dates: []};
        },
    });
    const data = view.data();

    assert.match(view.editTemplateContent, /data-action="importHolidayDates"/);
    assert.equal(typeof data.importLabel, 'string');
    assert.notEqual(data.importLabel, 'Import holiday dates');
    assert.deepEqual(plain(data.holidayRows), [{
        displayDate: '05.01.2026',
        isoDate: '2026-01-05',
        name: 'Ajunul Bobotezei',
        type: 'legal',
        source: 'zile-sarbatoare',
        typeLabel: 'Sărbătoare legală',
        badgeClass: 'label-success',
    }]);
    assert.equal(requests, 0, 'setup and render must not import holidays');

    model.set('year', 2026, {sync: true});
    model.set('selectedMonths', ['2', '3'], {ui: true});
    model.set('randomness', 8, {ui: true});
    model.set('sourceFileId', 'attachment-id', {ui: true});
    model.set('name', 'Generation', {ui: true});
    model.set('year', 2026, {ui: true});
    assert.equal(requests, 0, 'initial hydration and unrelated changes must not import holidays');
    assert.deepEqual(inputValues(view), ['05.01.2026']);
    assert.equal(view.confirmations, undefined, 'initial hydration and same-year values must not prompt');
}

{
    const importedDetail = {
        date: '2026-01-05',
        name: 'Ajunul Bobotezei',
        type: 'legal',
        source: 'zile-sarbatoare',
    };
    const {view, model, modelValues} = createView({
        locale: 'en_US',
        storedValue: '03.01.2026, 05.01.2026',
        storedDetails: [
            {date: '2026-01-03', name: '', type: 'internal', source: 'manual'},
            importedDetail,
        ],
        values: ['03.01.2026', '05.01.2026'],
        rowDetails: [null, importedDetail],
    });
    view.lastValidationMessage = 'Previous validation error';
    view.holidayImportPending = true;
    view.element.importButton.disabled = true;

    model.set('year', 2027, {ui: true});
    await flushMicrotasks();

    assert.equal(modelValues.year, 2027, 'confirmation must keep the committed new year');
    assert.equal(view.confirmations.length, 1);
    assert.deepEqual(
        {
            message: view.confirmations[0].message,
            confirmText: view.confirmations[0].confirmText,
            cancelText: view.confirmations[0].cancelText,
            confirmStyle: view.confirmations[0].confirmStyle,
            backdrop: view.confirmations[0].backdrop,
        },
        {
            message: 'Changing the year from 2026 to 2027 will remove all holiday dates and imported details. Continue?',
            confirmText: 'Clear holiday dates',
            cancelText: 'Keep current year',
            confirmStyle: 'warning',
            backdrop: 'static',
        },
        'the English confirmation must describe the destructive reset explicitly'
    );
    assert.deepEqual(inputValues(view), [''], 'the reset must leave one clean blank edit row');
    assert.equal(view.element.hidden.value, '', 'the hidden serialized value must be cleared');
    assert.deepEqual(plain(view.fetch()), {holidays: null, holidayDetails: []});
    assert.equal(modelValues.holidays, null);
    assert.deepEqual(plain(modelValues.holidayDetails), []);
    assert.equal(view.element.container.rows[0].dataset.holidaySource, 'manual');
    assert.equal(view.element.container.rows[0].dataset.holidayName, '');
    assert.doesNotMatch(view.element.container.rows[0].metadata.innerHTML, /Ajunul Bobotezei/);
    assert.equal(view.holidayImportPending, false, 'reset must clear the pending-import marker');
    assert.equal(view.element.importButton.disabled, false, 'reset must restore the import action');
    assert.equal(view.lastValidationMessage, null, 'reset must clear holiday validation residue');
}

{
    const importedDetail = {
        date: '2026-01-05',
        name: 'Ajunul Bobotezei',
        type: 'legal',
        source: 'zile-sarbatoare',
    };
    const {view, model, modelValues} = createView({
        confirmation: 'cancel',
        storedValue: '05.01.2026',
        storedDetails: [importedDetail],
        values: ['05.01.2026'],
        rowDetails: [importedDetail],
    });

    model.set('year', 2027, {ui: true});
    await flushMicrotasks();

    assert.equal(modelValues.year, 2026, 'cancelling must restore the previous year');
    assert.deepEqual(inputValues(view), ['05.01.2026']);
    assert.equal(view.element.hidden.value, '05.01.2026');
    assert.equal(modelValues.holidays, '05.01.2026');
    assert.deepEqual(modelValues.holidayDetails, [importedDetail]);
    assert.deepEqual(plain(view.fetch()), {
        holidays: '05.01.2026',
        holidayDetails: [importedDetail],
    });
    assert.equal(view.element.container.rows[0].dataset.holidaySource, 'zile-sarbatoare');
    assert.match(view.element.container.rows[0].metadata.innerHTML, /Ajunul Bobotezei/);
    assert.equal(
        view.confirmations[0].message,
        'Schimbarea anului din 2026 în 2027 va șterge toate zilele libere și detaliile preluate. Continui?'
    );
    assert.equal(view.confirmations[0].confirmText, 'Șterge zilele libere');
    assert.equal(view.confirmations[0].cancelText, 'Păstrează anul curent');
}

{
    const {view, model, modelValues} = createView();

    model.set('year', 2027, {ui: true});

    assert.equal(modelValues.year, 2027);
    assert.equal(view.confirmations, undefined, 'an empty holiday list must change year without prompting');
    assert.deepEqual(inputValues(view), ['']);
    assert.deepEqual(plain(view.fetch()), {holidays: null, holidayDetails: []});
}

{
    let releaseImport;
    const importResponse = new Promise(resolve => { releaseImport = resolve; });
    const {view, model} = createView({
        storedValue: '03.01.2026',
        storedDetails: [{date: '2026-01-03', name: '', type: 'internal', source: 'manual'}],
        values: ['03.01.2026'],
        ajax: () => importResponse,
    });
    const pendingImport = clickImport(view);

    model.set('year', 2027, {ui: true});
    await flushMicrotasks();
    releaseImport({
        dates: ['2026-01-01'],
        holidays: [{
            date: '2026-01-01',
            name: 'Anul Nou',
            type: 'legal',
            source: 'zile-sarbatoare',
        }],
    });
    await pendingImport;

    assert.deepEqual(
        inputValues(view),
        [''],
        'an old-year import response must not repopulate holidays after reset'
    );
    assert.deepEqual(plain(view.fetch()), {holidays: null, holidayDetails: []});
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
        holidays: [
            {
                date: '2026-01-01',
                name: 'Anul Nou',
                type: 'legal',
                source: 'zile-sarbatoare',
            },
            {
                date: '2026-01-05',
                name: 'Sărbătoare existentă',
                type: 'legal',
                source: 'zile-sarbatoare',
            },
        ],
    });
    await Promise.all([firstClick, secondClick]);

    assert.deepEqual(
        inputValues(view),
        ['05.01.2026', '01.01.2026'],
        'manual dates must remain and placeholder blank rows must be removed after import'
    );
    assert.equal(view.element.hidden.value, '05.01.2026, 01.01.2026');
    assert.deepEqual(plain(view.fetch().holidayDetails), [
        {
            date: '2026-01-05',
            name: 'Sărbătoare existentă',
            type: 'legal',
            source: 'zile-sarbatoare',
        },
        {
            date: '2026-01-01',
            name: 'Anul Nou',
            type: 'legal',
            source: 'zile-sarbatoare',
        },
    ]);
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

{
    const {view} = createView({
        ajax: async () => ({
            dates: ['2026-01-03'],
            holidays: [{
                date: '2026-01-03',
                name: 'Închidere companie',
                type: 'internal',
                source: 'zile-sarbatoare',
            }],
        }),
    });

    await clickImport(view);
    assert.deepEqual(plain(view.fetch().holidayDetails), [{
        date: '2026-01-03',
        name: 'Închidere companie',
        type: 'internal',
        source: 'zile-sarbatoare',
    }]);
    assert.deepEqual(plain(view.buildHolidayRow('03.01.2026', view.fetch().holidayDetails[0])), {
        displayDate: '03.01.2026',
        isoDate: '2026-01-03',
        name: 'Închidere companie',
        type: 'internal',
        source: 'zile-sarbatoare',
        typeLabel: 'Zi liberă internă',
        badgeClass: 'label-info',
    });

    const importedInput = view.element.querySelectorAll('input.holiday-date')[0];
    importedInput.value = '04.01.2026';
    view.handleDateInput(importedInput);
    assert.deepEqual(plain(view.fetch().holidayDetails), [{
        date: '2026-01-04',
        name: '',
        type: 'internal',
        source: 'manual',
    }], 'editing an imported date must convert it into a manual internal day');
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

    if (testCase.error && typeof testCase.error === 'object') {
        assert.equal(
            testCase.error.errorIsHandled,
            true,
            'custom import errors must suppress EspoCRM global Ajax error notifications'
        );
    }
}

{
    const xhr = {status: 405};
    const wrappedError = {xhr};
    const {view} = createView({
        ajax: async () => { throw wrappedError; },
    });

    await clickImport(view);
    assert.equal(wrappedError.errorIsHandled, true);
    assert.equal(xhr.errorIsHandled, true);
    assert.equal(lastNotification(), translations.ro_RO.messages.holidayImportUnavailable);
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

    assert.deepEqual(plain(view.fetch()), {
        holidays: '03.01.2026, 04.01.2026',
        holidayDetails: [
            {date: '2026-01-03', name: '', type: 'internal', source: 'manual'},
            {date: '2026-01-04', name: '', type: 'internal', source: 'manual'},
        ],
    });
    assert.equal(view.validateHolidayDates(), false);

    const firstRow = view.element.container.rows[0];
    view.removeHolidayDate(firstRow);
    assert.deepEqual(inputValues(view), ['04.01.2026']);
}

console.log('Holiday import offline contracts passed; production AMD field executed.');
