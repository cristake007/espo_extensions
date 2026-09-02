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
    'generator-perioade-cursuri-word-comparator/record'
);
const recordUiPath = path.join(
    extensionRoot,
    'files/client/custom/modules/generator-perioade-cursuri/src/views/shared/record-ui.js'
);

let RecordUi;
vm.runInNewContext(fs.readFileSync(recordUiPath, 'utf8'), {
    define(name, dependencies, factory) {
        RecordUi = factory();
    },
}, {filename: recordUiPath});

function loadView(file, BaseView) {
    let ViewClass;
    const context = {
        define(name, dependencies, factory) {
            const implementations = dependencies.map(dependency =>
                dependency === 'generator-perioade-cursuri:views/shared/record-ui' ?
                    RecordUi : BaseView
            );

            ViewClass = factory(...implementations);
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

let checks = 0;

function makeDetail() {
    const detail = new DetailView();
    detail.translate = value => value;

    return detail;
}

// --- Class-scope and button-state behavior, mirroring the Word Matcher's own
// review page so both entities feel like the same product.

const detailClasses = new Set();
const compareButton = {disabled: true, title: 'old title', classList: {toggle() {}}};
const detail = makeDetail();
detail.model = {id: 'saved-record-id', get: () => undefined};
detail.element = {
    classList: {add(value) { detailClasses.add(value); }},
    querySelector(selector) {
        return selector === '[data-action="compareWord"]' ? compareButton : null;
    },
};
detail.setup();
detail.afterRender();

checks++;
assert.equal(detail.isWide, true, 'comparator detail must use the wide record layout');
checks++;
assert.equal(detail.sideDisabled, true, 'comparator detail must disable the side column');
checks++;
assert.ok(detailClasses.has('generator-perioade-cursuri-word-comparator-page'), 'comparator detail must apply its page scope');
checks++;
assert.equal(compareButton.disabled, false, 'a saved record must allow comparison');
checks++;
assert.equal(compareButton.title, '', 'an enabled compare button must not retain an unavailable tooltip');

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

checks++;
assert.equal(edit.isWide, true, 'comparator edit must use the wide record layout');
checks++;
assert.equal(edit.sideDisabled, true, 'comparator edit must disable the side column');
checks++;
assert.ok(editClasses.has('generator-perioade-cursuri-word-comparator-page'), 'comparator edit must apply its page scope');

// --- Row composition: this is where the client-side diff (normalizeTitle,
// numeric-tolerant duration/price compare) actually lives now, so it is
// exercised directly through the row-rendering methods rather than mocked out.

function rowDetail(excelOptions) {
    const view = makeDetail();
    view.wordComparisonData = {excelOptions: excelOptions};
    view.wordComparisonSelections = {};

    return view;
}

// A clean exact match must render as unchanged, with the matching option
// pre-selected in the dropdown.
{
    const excelOptions = [{excelIndex: 0, title: 'Curs A', duration: '2 zile', price: '100 lei'}];
    const view = rowDetail(excelOptions);
    view.wordComparisonSelections = {0: 0};
    const row = {wordIndex: 0, title: 'Curs A', duration: '2 zile', price: '100 lei', candidates: []};
    const html = view.composeWordRowTr(row, {0: excelOptions[0]});

    checks++;
    assert.ok(html.includes('word-compare-row-same'), 'an exact match must render as unchanged');
    checks++;
    assert.ok(!html.includes('word-compare-cell-diff'), 'an exact match must not highlight any cell as different');
    checks++;
    assert.ok(html.includes('<option value="0" selected>'), 'the matched website course must be pre-selected in the dropdown');
}

// Regression: an en dash vs. a hyphen (and other punctuation-only differences)
// must not be reported as a title change. This is the exact case reported as a
// false positive against the shipped 2.7.2 build.
{
    const wordTitle = 'Responsabil conformare PPWR – Ambalaje și deșeuri de ambalaje';
    const excelTitle = 'Responsabil conformare PPWR - Ambalaje și deșeuri de ambalaje';
    const excelOptions = [{excelIndex: 1, title: excelTitle, duration: '2 zile', price: '200 euro'}];
    const view = rowDetail(excelOptions);
    view.wordComparisonSelections = {0: 1};
    const row = {wordIndex: 0, title: wordTitle, duration: '2 zile', price: '200 euro', candidates: []};
    const html = view.composeWordRowTr(row, {1: excelOptions[0]});

    checks++;
    assert.ok(html.includes('word-compare-row-same'), 'an en dash vs. a hyphen must not be reported as a course change');
    checks++;
    assert.ok(!html.includes('word-compare-cell-diff'), 'punctuation-only title differences must not highlight any cell');
}

// A genuine duration change must be highlighted, and both values must remain
// visible so the user can see exactly what changed.
{
    const excelOptions = [{excelIndex: 2, title: 'Curs B', duration: '5 zile', price: '150 lei'}];
    const view = rowDetail(excelOptions);
    view.wordComparisonSelections = {0: 2};
    const row = {wordIndex: 0, title: 'Curs B', duration: '3 zile', price: '150 lei', candidates: []};
    const html = view.composeWordRowTr(row, {2: excelOptions[0]});

    checks++;
    assert.ok(html.includes('word-compare-row-different'), 'a real duration change must mark the row as changed');
    checks++;
    assert.ok(html.includes('word-compare-cell-diff'), 'a real duration change must highlight the differing cells');
    checks++;
    assert.ok(html.includes('>3 zile<'), 'the Word duration value must remain visible');
    checks++;
    assert.ok(html.includes('>5 zile<'), 'the Website duration value must remain visible');
}

// A Word row with no selection (nothing picked yet, or the algorithm found no
// confident default) must render as missing, with its quick-pick candidates
// available so the user can resolve it manually.
{
    const view = rowDetail([]);
    view.wordComparisonSelections = {0: null};
    const row = {
        wordIndex: 0,
        title: 'Curs C',
        duration: '1 zi',
        price: '50 lei',
        candidates: [{excelIndex: 9, title: 'Curs Aproape Identic', score: 40}],
    };
    const html = view.composeWordRowTr(row, {});

    checks++;
    assert.ok(html.includes('word-compare-row-missing'), 'an unresolved Word row must render as missing');
    checks++;
    assert.ok(html.includes('rowStatusMissingWebsite'), 'an unresolved Word row must use the missing-on-website status label');
    checks++;
    assert.ok(
        html.includes('data-candidate-excel-index="9"'),
        'a low-confidence candidate must still be offered as a manual quick-pick'
    );
}

// An unassigned website course (nothing points to it) is still shown, in the
// same table, as a plain informational row.
{
    const view = rowDetail([]);
    const option = {excelIndex: 5, title: 'Curs D', duration: '2 zile', price: '80 lei'};
    const html = view.composeExcelOnlyTr(option);

    checks++;
    assert.ok(html.includes('word-compare-row-missing'), 'an unassigned website course must render as missing');
    checks++;
    assert.ok(html.includes('rowStatusMissingWord'), 'an unassigned website course must use the missing-in-Word status label');
    checks++;
    assert.ok(html.includes('Curs D'), 'an unassigned website course must show its own title');
}

console.log(`Word comparator view state: ${checks} checks passed.`);
