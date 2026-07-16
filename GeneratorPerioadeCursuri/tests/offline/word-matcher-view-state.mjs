import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import {fileURLToPath} from 'node:url';

const testDirectory = path.dirname(fileURLToPath(import.meta.url));
const extensionRoot = path.resolve(testDirectory, '../..');
const viewRoot = path.join(
    extensionRoot,
    'files/client/custom/modules/generator-perioade-cursuri/src/views/' +
    'generator-perioade-cursuri-word-matcher/record'
);

function loadView(file, BaseView) {
    let ViewClass;
    const context = {
        define(name, dependencies, factory) {
            ViewClass = factory(BaseView);
        },
    };

    vm.runInNewContext(fs.readFileSync(path.join(viewRoot, file), 'utf8'), context, {
        filename: path.join(viewRoot, file),
    });

    return ViewClass;
}

class DetailRecordView {
    setup() {}
    afterRender() {}
    addButton() {}
}

const DetailView = loadView('detail.js', DetailRecordView);
const detail = new DetailView();
const detailClasses = new Set();
const previewButton = {
    disabled: true,
    title: 'old title',
    classList: {toggle() {}},
};

detail.model = {
    id: 'saved-record-id',
    get() {
        return undefined;
    },
};
detail.element = {
    classList: {add(value) { detailClasses.add(value); }},
    querySelector(selector) {
        return selector === '[data-action="previewWordConversion"]' ? previewButton : null;
    },
};
detail.translate = value => value;
detail.setup();
detail.afterRender();

assert.equal(detail.isWide, true, 'matcher detail must use the wide record layout');
assert.equal(detail.sideDisabled, true, 'matcher detail must disable the side column');
assert.ok(detailClasses.has('generator-perioade-cursuri-word-matcher-page'), 'matcher detail must apply its page scope');
assert.equal(previewButton.disabled, false, 'a saved record must allow review when cached file attributes are absent');
assert.equal(previewButton.title, '', 'an enabled review button must not retain an unavailable tooltip');

class GeneratorEditRecordView {
    setup() {}
    afterRender() {}
}

const EditView = loadView('edit.js', GeneratorEditRecordView);
const edit = new EditView();
const editClasses = new Set();
edit.element = {classList: {add(value) { editClasses.add(value); }}};
edit.setup();
edit.afterRender();

assert.equal(edit.isWide, true, 'matcher edit must use the wide record layout');
assert.equal(edit.sideDisabled, true, 'matcher edit must disable the side column');
assert.ok(editClasses.has('generator-perioade-cursuri-word-matcher-page'), 'matcher edit must apply its page scope');

console.log('Word matcher view state: 8 checks passed.');
