'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const extensionRoot = path.resolve(__dirname, '../..');
const sourceRoot = path.join(extensionRoot, 'files/client/custom/modules/document-builder/src');
const cache = new Map();
let activeDefinition = null;

function define(dependencies, factory) {
    activeDefinition = {dependencies, factory};
}

function load(moduleName) {
    if (cache.has(moduleName)) return cache.get(moduleName);

    const filename = path.join(sourceRoot, `${moduleName.replace(/^document-builder:/, '')}.js`);
    activeDefinition = null;
    new Function('define', `${fs.readFileSync(filename, 'utf8')}\n//# sourceURL=${filename}`)(define);
    const definition = activeDefinition;
    const exported = definition.factory(...definition.dependencies.map(load));
    cache.set(moduleName, exported);

    return exported;
}

const PageGeometry = load('document-builder:editor/geometry/page-geometry');
const EditorState = load('document-builder:editor/state/editor-state');
const UpdateDocumentCommand = load('document-builder:editor/commands/update-document');
const LayoutPrecheck = load('document-builder:editor/validation/layout-precheck');
const PageSettings = load('document-builder:editor/page-settings');
const layout = JSON.parse(fs.readFileSync(
    path.join(extensionRoot, 'tests/fixtures/layout/phase-08-default.json'),
    'utf8',
));

const geometry = new PageGeometry();
assert.deepEqual(geometry.getSizeList().map(item => item.id), ['A4', 'A3']);
assert.ok(Math.abs(geometry.millimetresToPixels(25.4) - 96) < 1e-10);
assert.equal(geometry.frame('A4', 'portrait', 100).widthMm, 210);
assert.equal(geometry.frame('A4', 'landscape', 100).widthMm, 297);
assert.equal(geometry.clampZoom(1), 25);
assert.equal(geometry.clampZoom(999), 200);
assert.equal(geometry.fitWidth('A4', 'portrait', 1), 25);
assert.equal(geometry.fitPage('A4', 'portrait', 10000, 10000), 200);

const customGeometry = new PageGeometry([
    {id: 'Badge', label: 'Badge', widthMm: 90, heightMm: 55},
]);
assert.equal(customGeometry.frame('Badge', 'landscape').widthMm, 55);
assert.throws(() => geometry.frame('Badge', 'portrait'), /unsupported/);

const legacyLayout = JSON.parse(JSON.stringify(layout));
delete legacyLayout.document.defaults.timezone;
delete legacyLayout.document.titlePattern;
delete legacyLayout.document.filenamePattern;
const normalizedLegacy = PageSettings.normalize(legacyLayout);
assert.equal(normalizedLegacy.document.defaults.timezone, 'UTC');
assert.equal(normalizedLegacy.document.titlePattern, 'Document');
assert.equal(normalizedLegacy.document.filenamePattern, 'document.pdf');
assert.equal(new LayoutPrecheck().check(normalizedLegacy).valid, true);

const state = new EditorState(layout);
const updatedDocument = state.getLayout().document;
updatedDocument.page.orientation = 'landscape';
assert.equal(state.execute(new UpdateDocumentCommand(updatedDocument)), true);
assert.equal(state.getLayout().document.page.orientation, 'landscape');
assert.equal(state.undo(), true);
assert.equal(state.getLayout().document.page.orientation, 'portrait');
assert.equal(state.redo(), true);
assert.equal(state.getLayout().document.page.orientation, 'landscape');

const beforeZoom = JSON.stringify(state.getLayout());
geometry.frame('A4', 'landscape', 25);
geometry.frame('A4', 'landscape', 200);
assert.equal(JSON.stringify(state.getLayout()), beforeZoom, 'Zoom mutated canonical layout values.');

const customLayout = JSON.parse(JSON.stringify(layout));
customLayout.document.page.size = 'Badge';
assert.equal(new LayoutPrecheck().check(customLayout).valid, false);
assert.equal(new LayoutPrecheck([
    {id: 'Badge', label: 'Badge', widthMm: 90, heightMm: 55},
]).check(customLayout).valid, true);

console.log('Phase 18 page geometry, zoom invariance, settings command, and custom-size tests passed.');
