'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const sourceRoot = path.join(root, 'files/client/custom/modules/document-builder/src');
const cache = new Map(); let active;
function define(dependencies, factory) { active = {dependencies, factory}; }
function load(name) {
    if (cache.has(name)) return cache.get(name);
    active = null;
    const file = path.join(sourceRoot, `${name.replace(/^document-builder:/, '')}.js`);
    new Function('define', fs.readFileSync(file, 'utf8'))(define);
    const value = active.factory(...active.dependencies.map(load)); cache.set(name, value); return value;
}
const BrowserRenderer = load('document-builder:editor/renderer/browser-renderer');
const EditorValidator = load('document-builder:editor/validation/editor-validator');
const PageGeometry = load('document-builder:editor/geometry/page-geometry');
const StyleResolver = load('document-builder:editor/style/style-resolver');
const layout = JSON.parse(fs.readFileSync(path.join(root, 'tests/fixtures/layout/phase-08-default.json')));
const box = () => Object.fromEntries(['top', 'right', 'bottom', 'left'].map(edge => [edge, {value: 0, unit: 'mm'}]));
layout.capabilities = ['layout.flow'];
layout.sections = [{
    id: 'section', type: 'flow-section', children: [
        {id: 'heading', type: 'heading', content: [{type: 'text', text: 'Titlu', marks: []}], level: 2, keepWithNext: true},
        {id: 'emptyParagraph', type: 'paragraph', content: [{type: 'text', text: '', marks: []}], alignment: 'start'},
        {id: 'pageBreak', type: 'page-break'},
        {id: 'text', type: 'static-text', text: 'Conținut pe pagina următoare'},
    ], margin: box(), padding: box(), minHeight: {value: 20, unit: 'mm'},
    keepTogether: false, startNewPage: false,
}];
const before = JSON.stringify(layout);
const renderer = new BrowserRenderer({
    pageGeometry: new PageGeometry(),
    styleResolver: new StyleResolver(['DejaVu Sans']),
});
const result = renderer.render(layout, {selectedId: 'heading', zoom: 125});
assert.equal(JSON.stringify(layout), before, 'Rendering must not mutate canonical state.');
assert.equal(result.rows.find(row => row.id === 'heading').selected, true);
assert.equal(result.rows.find(row => row.id === 'emptyParagraph').isEmpty, true);
assert.equal(result.rows.find(row => row.id === 'emptyParagraph').sampleKey, 'editorParagraphSample');
assert.equal(result.rows.find(row => row.id === 'pageBreak').startsPage, true);
assert.equal(result.rows.find(row => row.id === 'text').pageNumber, 2);
assert.equal(result.rows.find(row => row.id === 'section').badgeLabel, 'Structure');
assert.equal(result.rows.find(row => row.id === 'heading').badgeLabel, 'Element');
assert.equal(result.rows.find(row => row.id === 'heading').iconClass, 'fa-heading');
assert.equal(result.rows.find(row => row.id === 'heading').depthLabel, 2);
assert.equal(result.rows.find(row => row.id === 'heading').hasParent, true);
assert.equal(result.rows.find(row => row.id === 'section').childCount, 4);
assert.match(result.rows.find(row => row.id === 'heading').flowStyle, /--document-builder-margin-left: 0px/);
const validation = new EditorValidator().validate(layout);
assert.equal(validation.blocking, false);
assert.equal(validation.warningCount, 1);
assert.equal(validation.issues[0].nodeId, 'emptyParagraph');
const invalid = JSON.parse(before);
invalid.sections[0].children[0].level = 7;
const invalidValidation = new EditorValidator().validate(invalid);
assert.equal(invalidValidation.blocking, true);
assert.equal(invalidValidation.errorCount > 0, true);
assert.equal(invalidValidation.issues.find(issue => issue.severity === 'error').nodeId, 'heading');
console.log('Phase 23 renderer purity, samples, page flow, badges, and actionable severity tests passed.');
