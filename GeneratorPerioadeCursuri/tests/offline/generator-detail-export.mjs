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
    setup() {
        this.baseSetupCount = (this.baseSetupCount ?? 0) + 1;
    }

    afterRender() {
        this.baseAfterRenderCount = (this.baseAfterRenderCount ?? 0) + 1;
    }

    addButton() {}
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

const buttonStates = [];
const requests = [];
const openedUrls = [];
const RecordUi = {
    escapeHtml(value) {
        return String(value);
    },
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
    view.element = {classList: new FakeClassList()};
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

    view.setup();
    assert.equal(view.isWide, true, 'saved detail must use EspoCRM wide-record mode');
    assert.equal(view.sideDisabled, true, 'saved detail must not create an empty side view');
    assert.equal(view.baseSetupCount, 1);
    view.afterRender();
    assert.equal(view.baseAfterRenderCount, 1);
    assert.equal(
        view.element.classList.contains('generator-perioade-cursuri-page'),
        true,
        'saved detail must apply the main-entity page class'
    );
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

{
    const view = createView({});
    const courseRows = view.buildCourseRows([
        {
            sourceRow: 2,
            originalOrder: 0,
            title: 'Măsurarea eficacității unui sistem',
            durationLabel: '2 zile',
            investment: '100',
            month: 1,
            dateRange: '05-06.01.2026',
        },
        {
            sourceRow: 2,
            originalOrder: 0,
            title: 'Măsurarea eficacității unui sistem',
            durationLabel: '2 zile',
            investment: '100',
            month: 2,
            dateRange: '02-03.02.2026',
        },
    ]);

    assert.deepEqual(plain(courseRows), [{
        originalOrder: 0,
        title: 'Măsurarea eficacității unui sistem',
        durationLabel: '2 zile',
        investment: '100',
        months: {
            1: '05-06.01.2026',
            2: '02-03.02.2026',
        },
    }]);
    assert.equal('courseTitle' in courseRows[0], false, 'preview rows must keep the canonical title key');
    assert.match(
        view.composeGeneratedScheduleRow(courseRows[0], [{value: '1'}], 0),
        /Măsurarea eficacității unui sistem/,
        'preview rendering must consume the canonical title value'
    );
}

console.log('Generator detail XLSX export contracts passed; production AMD view executed.');
