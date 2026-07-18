import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import {fileURLToPath} from 'node:url';

const testDirectory = path.dirname(fileURLToPath(import.meta.url));
const extensionRoot = path.resolve(testDirectory, '../..');
const viewPath = path.join(
    extensionRoot,
    'files/client/custom/modules/generator-perioade-cursuri/src/views/' +
        'generator-perioade-cursuri/record/edit.js'
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

const notifications = [];
const Espo = {
    Ui: {
        error(message) {
            notifications.push(message);
        },
    },
};

class FakeModel {
    constructor(attributes = {}, isNew = true) {
        this.attributes = {...attributes};
        this.newRecord = isNew;
        this.saveCount = 0;
    }

    isNew() {
        return this.newRecord;
    }

    get(name) {
        return this.attributes[name];
    }

    setMultiple(attributes) {
        Object.assign(this.attributes, attributes);
    }

    async save(attributes) {
        this.saveCount++;
        Object.assign(this.attributes, attributes);
    }
}

class FakeClassList {
    constructor() {
        this.values = new Set();
    }

    add(value) {
        this.values.add(value);
    }

    contains(value) {
        return this.values.has(value);
    }
}

class NativeFieldView {
    constructor(recordView, name) {
        this.recordView = recordView;
        this.name = name;
    }

    validate() {
        this.recordView.validationCalls[this.name]++;

        if (this.name === 'name') {
            const value = this.recordView.model.get('name');

            if (value === null || value === '') {
                this.recordView.inlineErrors.push(['name', 'fieldIsRequired']);
                this.recordView.focusedFields.push('name');

                return true;
            }

            if (this.recordView.namePatternInvalid) {
                this.recordView.inlineErrors.push(['name', 'fieldDoesNotMatchPattern']);
                this.recordView.focusedFields.push('name');

                return true;
            }

            return false;
        }

        if (this.recordView.unrelatedFieldInvalid) {
            this.recordView.inlineErrors.push([this.name, 'fieldInvalid']);
            this.recordView.focusedFields.push(this.name);

            return true;
        }

        return false;
    }
}

class EditRecordView {
    constructor({
        locale = 'en_US',
        nameInput = '',
        namePatternInvalid = false,
        unrelatedFieldInvalid = false,
        entityType = 'GeneratorPerioadeCursuri',
        isNew = true,
    } = {}) {
        this.locale = locale;
        this.nameInput = nameInput;
        this.namePatternInvalid = namePatternInvalid;
        this.unrelatedFieldInvalid = unrelatedFieldInvalid;
        this.entityType = entityType;
        this.scope = entityType;
        this.model = new FakeModel({name: null}, isNew);
        this.element = {classList: new FakeClassList()};
        this.validationCalls = {name: 0, holidays: 0};
        this.inlineErrors = [];
        this.focusedFields = [];
        this.baseInvalidLists = [];
        this.baseAfterNotValidCount = 0;
        this.enableActionItemsCount = 0;
        this.fieldViews = {
            name: new NativeFieldView(this, 'name'),
            holidays: new NativeFieldView(this, 'holidays'),
        };
    }

    setup() {
        this.baseSetupCount = (this.baseSetupCount ?? 0) + 1;
    }

    afterRender() {}

    translate(key, category, scope) {
        if (scope === 'GeneratorPerioadeCursuri') {
            return translations[this.locale][category]?.[key] ?? key;
        }

        return key;
    }

    fetch() {
        const value = this.nameInput.trim();

        return {name: value || null};
    }

    getFieldList() {
        return ['name', 'holidays'];
    }

    validateField(name) {
        return this.fieldViews[name].validate();
    }

    validate() {
        const invalidFieldList = [];

        this.getFieldList().forEach(field => {
            if (this.validateField(field)) {
                invalidFieldList.push(field);
            }
        });

        if (invalidFieldList.length) {
            this.onInvalid(invalidFieldList);
        }

        return invalidFieldList.length > 0;
    }

    onInvalid(invalidFieldList) {
        this.baseInvalidLists.push(invalidFieldList.slice());
    }

    afterNotValid() {
        this.baseAfterNotValidCount++;
        Espo.Ui.error(this.translate('Not valid'));
        this.enableActionItems();
    }

    enableActionItems() {
        this.enableActionItemsCount++;
    }

    async save() {
        const fetchedAttributes = this.fetch();

        this.model.setMultiple(fetchedAttributes, {silent: true});

        if (this.validate()) {
            this.afterNotValid();

            throw 'invalid';
        }

        await this.model.save(fetchedAttributes);
    }
}

let GeneratorEditView;
vm.runInNewContext(fs.readFileSync(viewPath, 'utf8'), {
    Espo,
    define(name, dependencies, factory) {
        assert.equal(
            name,
            'generator-perioade-cursuri:views/generator-perioade-cursuri/record/edit'
        );
        assert.deepEqual(Array.from(dependencies), ['views/record/edit']);
        GeneratorEditView = factory(EditRecordView);
    },
}, {filename: viewPath});

assert.equal(typeof GeneratorEditView, 'function', 'the production edit view must load through AMD');

for (const isNew of [true, false]) {
    const view = new GeneratorEditView({isNew});

    view.setup();
    assert.equal(view.isWide, true, 'main-entity create and saved edit must use wide-record mode');
    assert.equal(view.sideDisabled, true, 'main-entity create and saved edit must disable the empty side view');
    view.afterRender();
    assert.equal(
        view.element.classList.contains('generator-perioade-cursuri-page'),
        true,
        'main-entity create and saved edit must share the semantic page class'
    );
    assert.equal(
        view.element.classList.contains('generator-perioade-cursuri-create'),
        false,
        'the main entity must not use a create-only class for shared page styling'
    );
}

{
    const createView = new GeneratorEditView({
        entityType: 'GeneratorPerioadeCursuriXmlConverter',
        isNew: true,
    });
    const savedView = new GeneratorEditView({
        entityType: 'GeneratorPerioadeCursuriXmlConverter',
        isNew: false,
    });

    createView.setup();
    createView.afterRender();
    savedView.setup();
    savedView.afterRender();

    assert.equal(createView.isWide, true, 'inherited create views must retain their existing wide behavior');
    assert.equal(createView.sideDisabled, true);
    assert.equal(createView.element.classList.contains('generator-perioade-cursuri-create'), true);
    assert.equal(createView.element.classList.contains('generator-perioade-cursuri-page'), false);
    assert.notEqual(savedView.isWide, true, 'saved inherited views must not gain the main-entity layout contract');
    assert.notEqual(savedView.sideDisabled, true);
    assert.equal(savedView.element.classList.contains('generator-perioade-cursuri-page'), false);
}

function plain(value) {
    return JSON.parse(JSON.stringify(value));
}

async function attemptSave(options) {
    notifications.length = 0;

    const view = new GeneratorEditView(options);
    let rejection = null;

    view.setup();

    try {
        await view.save();
    } catch (reason) {
        rejection = reason;
    }

    return {view, rejection, notifications: notifications.slice()};
}

for (const testCase of [
    {locale: 'en_US', nameInput: '', message: 'Generation Name is required.'},
    {locale: 'en_US', nameInput: '   ', message: 'Generation Name is required.'},
    {locale: 'ro_RO', nameInput: '', message: 'Denumirea generării este obligatorie.'},
]) {
    const {view, rejection, notifications: errors} = await attemptSave(testCase);

    assert.equal(rejection, 'invalid', 'a missing Generation Name must block the native save path');
    assert.deepEqual(errors, [testCase.message]);
    assert.equal(view.model.get('name'), null, 'native varchar fetch must normalize blank input to null');
    assert.deepEqual(view.validationCalls, {name: 1, holidays: 1});
    assert.deepEqual(plain(view.inlineErrors), [['name', 'fieldIsRequired']]);
    assert.deepEqual(view.focusedFields, ['name']);
    assert.deepEqual(plain(view.baseInvalidLists), [['name']]);
    assert.equal(view.baseAfterNotValidCount, 0, 'the generic toast hook must be suppressed for required name');
    assert.equal(view.enableActionItemsCount, 1, 'save actions must be restored after failed validation');
    assert.equal(view.model.saveCount, 0, 'invalid input must not invoke model persistence');
}

{
    const {view, rejection, notifications: errors} = await attemptSave({
        nameInput: 'Valid generation',
    });

    assert.equal(rejection, null);
    assert.deepEqual(errors, []);
    assert.deepEqual(view.validationCalls, {name: 1, holidays: 1});
    assert.deepEqual(view.inlineErrors, []);
    assert.equal(view.model.saveCount, 1, 'a valid name must continue through native model persistence');
}

{
    const {view, rejection, notifications: errors} = await attemptSave({
        nameInput: 'Valid generation',
        unrelatedFieldInvalid: true,
    });

    assert.equal(rejection, 'invalid');
    assert.deepEqual(errors, ['Not valid']);
    assert.deepEqual(view.validationCalls, {name: 1, holidays: 1});
    assert.deepEqual(plain(view.inlineErrors), [['holidays', 'fieldInvalid']]);
    assert.deepEqual(plain(view.baseInvalidLists), [['holidays']]);
    assert.equal(view.baseAfterNotValidCount, 1, 'unrelated failures must retain native generic feedback');
    assert.equal(view.model.saveCount, 0);
}

{
    const {view, rejection, notifications: errors} = await attemptSave({
        nameInput: 'Invalid pattern',
        namePatternInvalid: true,
    });

    assert.equal(rejection, 'invalid');
    assert.deepEqual(errors, ['Not valid']);
    assert.deepEqual(plain(view.inlineErrors), [['name', 'fieldDoesNotMatchPattern']]);
    assert.equal(
        view.baseAfterNotValidCount,
        1,
        'a non-required name failure must not be mislabeled as a missing Generation Name'
    );
}

console.log('Generator edit validation contracts passed; production AMD view executed.');
