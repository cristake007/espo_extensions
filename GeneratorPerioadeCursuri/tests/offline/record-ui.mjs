import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import {fileURLToPath} from 'node:url';

const testDirectory = path.dirname(fileURLToPath(import.meta.url));
const extensionRoot = path.resolve(testDirectory, '../..');
const modulePath = path.join(
    extensionRoot,
    'files/client/custom/modules/generator-perioade-cursuri/src/views/shared/record-ui.js'
);

let RecordUi;
const moduleSource = fs.readFileSync(modulePath, 'utf8');
const context = {
    define(name, dependencies, factory) {
        assert.equal(name, 'generator-perioade-cursuri:views/shared/record-ui');
        assert.deepEqual(Array.from(dependencies), []);
        RecordUi = factory();
    },
};

vm.runInNewContext(moduleSource, context, {filename: modulePath});
assert.ok(RecordUi, 'the production shared record UI module must load through AMD');

let checks = 0;
const checkEqual = (actual, expected, message) => {
    checks++;
    assert.equal(actual, expected, message);
};

checkEqual(
    /\bmodel\b|Espo\.Ajax|Espo\.Ui|\btranslate\s*\(|\bnotify\s*\(/.test(moduleSource),
    false,
    'the shared helper must not own model, request, translation, or notification behavior'
);

checkEqual(RecordUi.escapeHtml(null), '', 'null must render as empty text');
checkEqual(RecordUi.escapeHtml(undefined), '', 'undefined must render as empty text');
checkEqual(RecordUi.escapeHtml(0), '0', 'numeric zero must be preserved');
checkEqual(
    RecordUi.escapeHtml('&<>"\''),
    '&amp;&lt;&gt;&quot;&#039;',
    'HTML-sensitive characters must be escaped'
);

const existingRegion = {dataset: {name: 'existing'}};
const existingRoot = {
    querySelector(selector) {
        return selector === '[data-name="existing"]' ? existingRegion : null;
    },
};
checkEqual(
    RecordUi.ensureRecordRegion(existingRoot, 'existing'),
    existingRegion,
    'an existing record region must be returned unchanged'
);

const createdRegions = [];
const recordElement = {
    appendChild(region) {
        createdRegions.push(region);
    },
};
const rootElement = {
    ownerDocument: {
        createElement(tagName) {
            return {tagName, dataset: {}};
        },
    },
    querySelector(selector) {
        if (selector === '.record') {
            return recordElement;
        }

        return null;
    },
};
const createdRegion = RecordUi.ensureRecordRegion(rootElement, 'results');
checkEqual(createdRegions.length, 1, 'one missing record region must be created');
checkEqual(createdRegions[0], createdRegion, 'the created record region must be returned');
checkEqual(createdRegion.dataset.name, 'results', 'the created record region must have its data name');

checkEqual(
    RecordUi.setActionButtonState({querySelector: () => null}, 'missing', true, 'Unavailable'),
    false,
    'a missing action button must be a no-op'
);

const toggles = [];
const button = {
    disabled: false,
    title: 'stale title',
    classList: {
        toggle(name, enabled) {
            toggles.push([name, enabled]);
        },
    },
};
const buttonRoot = {querySelector: () => button};
checkEqual(
    RecordUi.setActionButtonState(buttonRoot, 'run', true, 'Unavailable'),
    true,
    'an existing action button must be updated'
);
checkEqual(button.disabled, true, 'disabled state must use the native property');
checkEqual(button.title, 'Unavailable', 'disabled state must expose its explanation');
checkEqual(toggles[0].join(':'), 'disabled:true', 'disabled CSS state must mirror the property');

RecordUi.setActionButtonState(buttonRoot, 'run', false, 'Unused');
checkEqual(button.disabled, false, 'an enabled action button must clear the native disabled property');
checkEqual(button.title, '', 'an enabled action button must clear a stale title');
checkEqual(toggles[1].join(':'), 'disabled:false', 'enabled CSS state must mirror the property');

checkEqual(
    RecordUi.synchronizeHorizontalScroll({querySelector: () => null}, '.top', '.main'),
    false,
    'scroll synchronization must reject incomplete DOM regions'
);

const listeners = {};
const topInner = {style: {}};
const table = {scrollWidth: 720};
const topScroller = {
    firstElementChild: topInner,
    scrollLeft: 0,
    addEventListener(name, listener) {
        listeners.top = listener;
    },
};
const mainScroller = {
    scrollLeft: 0,
    querySelector: selector => selector === 'table' ? table : null,
    addEventListener(name, listener) {
        listeners.main = listener;
    },
};
const scrollContainer = {
    querySelector(selector) {
        return selector === '.top' ? topScroller : mainScroller;
    },
};
checkEqual(
    RecordUi.synchronizeHorizontalScroll(scrollContainer, '.top', '.main'),
    true,
    'complete scroll regions must be synchronized'
);
checkEqual(topInner.style.width, '720px', 'the top scroller must use the rendered table width');

topScroller.scrollLeft = 45;
listeners.top();
checkEqual(mainScroller.scrollLeft, 45, 'top scrolling must update the main scroller');

mainScroller.scrollLeft = 90;
listeners.main();
checkEqual(topScroller.scrollLeft, 90, 'main scrolling must update the top scroller');

console.log(`Shared record UI: ${checks} checks passed; production JavaScript executed.`);
