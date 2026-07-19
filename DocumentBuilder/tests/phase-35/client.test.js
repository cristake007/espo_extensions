'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const sourceRoot = path.join(root, 'files/client/custom/modules/document-builder/src');
const cache = new Map();
let active;
function define(dependencies, factory) { active = {dependencies, factory}; }
function load(name) {
    if (cache.has(name)) return cache.get(name);
    active = null;
    const file = path.join(sourceRoot, `${name.replace(/^document-builder:/, '')}.js`);
    new Function('define', fs.readFileSync(file, 'utf8'))(define);
    const value = active.factory(...active.dependencies.map(load));
    cache.set(name, value);
    return value;
}

const UpdatePageChromeCommand = load('document-builder:editor/commands/update-page-chrome');
const layout = {
    capabilities: [],
    document: {
        chrome: {
            header: {height: {value: 0, unit: 'mm'}, showOnFirstPage: true, disableOnFullPage: true},
            footer: {height: {value: 0, unit: 'mm'}, showOnFirstPage: true, disableOnFullPage: true},
        },
    },
    header: [], sections: [], footer: [],
};
let sequence = 0;
const context = {idFactory: {create: prefix => `${prefix}-${++sequence}`}};
const presentation = {format: {type: 'auto'}, missing: 'empty'};

new UpdatePageChromeCommand('header', {
    enabled: true,
    text: 'Invoice',
    includePageNumber: true,
    alignment: 'end',
    height: 10,
    showOnFirstPage: false,
    disableOnFullPage: true,
}, presentation).execute(layout, context);

assert.equal(layout.header.length, 1);
assert.equal(layout.header[0].type, 'paragraph');
assert.equal(layout.header[0].alignment, 'end');
assert.equal(layout.header[0].content[0].text, 'Invoice');
assert.deepEqual(layout.header[0].content[2].identity, {
    source: 'system', type: 'system', path: ['pageNumber'],
});
assert.equal(layout.document.chrome.header.height.value, 10);
assert.equal(layout.document.chrome.header.showOnFirstPage, false);
assert.deepEqual(layout.capabilities, ['layout.flow']);

layout.header.push({
    id: 'header-divider', type: 'divider', orientation: 'horizontal', lineStyle: 'solid',
    color: '#222222', thickness: {value: 0.2, unit: 'mm'}, length: {value: 100, unit: 'mm'},
});
new UpdatePageChromeCommand('header', {
    enabled: true,
    text: '',
    includePageNumber: false,
    alignment: 'center',
    height: 8,
    showOnFirstPage: true,
    disableOnFullPage: false,
    updateContent: false,
}, presentation).execute(layout, context);
assert.equal(layout.header.length, 2);
assert.equal(layout.header[0].content[0].text, 'Invoice');
assert.equal(layout.header[1].type, 'divider');
assert.equal(layout.document.chrome.header.height.value, 8);

new UpdatePageChromeCommand('header', {
    enabled: false,
    text: '',
    includePageNumber: false,
    alignment: 'start',
    height: 10,
    showOnFirstPage: true,
    disableOnFullPage: true,
}, presentation).execute(layout, context);
assert.deepEqual(layout.header, []);
assert.equal(layout.document.chrome.header.height.value, 0);
assert.deepEqual(layout.capabilities, []);

console.log('Phase 35 page chrome editor command tests passed.');
