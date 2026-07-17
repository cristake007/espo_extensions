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
        'generator-perioade-cursuri/record/detail.js'
);

class DetailRecordView {
    setup() {}

    addButton() {}
}

const buttonStates = [];
const requests = [];
const openedUrls = [];
const RecordUi = {
    setActionButtonState(element, action, disabled, title) {
        buttonStates.push({element, action, disabled, title});
    },
};
const Espo = {
    Ajax: {
        async postRequest(url, payload) {
            requests.push([url, payload]);

            return {downloadUrl: '?entryPoint=download&id=preserved-attachment'};
        },
    },
    Ui: {
        notify() {},
        error() {},
    },
};

let DetailView;
vm.runInNewContext(fs.readFileSync(viewPath, 'utf8'), {
    console,
    Espo,
    window: {
        open(url, target) {
            openedUrls.push([url, target]);
        },
    },
    define(name, dependencies, factory) {
        assert.equal(
            name,
            'generator-perioade-cursuri:views/generator-perioade-cursuri/record/detail'
        );
        assert.deepEqual(Array.from(dependencies), [
            'views/record/detail',
            'generator-perioade-cursuri:views/shared/record-ui',
        ]);
        DetailView = factory(DetailRecordView, RecordUi);
    },
}, {filename: viewPath});

function createView(values) {
    const view = new DetailView();
    view.element = {};
    view.model = {
        id: 'preserved-record',
        get(name) {
            return values[name];
        },
    };
    view.translate = key => key;

    return view;
}

function plain(value) {
    return JSON.parse(JSON.stringify(value));
}

{
    const view = createView({generatedAt: null, exportFileId: null});

    view.updateExportButtonState();
    assert.equal(buttonStates.at(-1).disabled, true, 'ungenerated records must keep export disabled');
}

{
    const view = createView({generatedAt: '2026-07-18 12:00:00', exportFileId: null});

    view.updateExportButtonState();
    assert.equal(
        buttonStates.at(-1).disabled,
        false,
        'generated records must allow server-side attachment lookup after reinstall'
    );

    await view.actionExportXlsx();
    assert.deepEqual(plain(requests.at(-1)), [
        'GeneratorPerioadeCursuri/preserved-record/exportXlsx',
        {},
    ]);
    assert.deepEqual(openedUrls.at(-1), [
        '?entryPoint=download&id=preserved-attachment',
        '_blank',
    ]);
}

{
    const view = createView({generatedAt: null, exportFileId: 'client-attachment'});

    view.updateExportButtonState();
    assert.equal(buttonStates.at(-1).disabled, false);

    await view.actionExportXlsx();
    assert.deepEqual(openedUrls.at(-1), [
        '?entryPoint=download&id=client-attachment',
        '_blank',
    ]);
}

console.log('Generator detail XLSX export contracts passed; production AMD view executed.');
