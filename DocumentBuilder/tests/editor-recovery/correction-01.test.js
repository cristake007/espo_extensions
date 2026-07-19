'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const sourceRoot = path.join(root, 'files/client/custom/modules/document-builder/src');
const cache = new Map();
let activeDefinition;

function define(dependencies, factory) { activeDefinition = {dependencies, factory}; }
function load(name) {
    if (cache.has(name)) return cache.get(name);
    activeDefinition = null;
    const file = path.join(sourceRoot, `${name.replace(/^document-builder:/, '')}.js`);
    new Function('define', fs.readFileSync(file, 'utf8'))(define);
    const value = activeDefinition.factory(...activeDefinition.dependencies.map(load));
    cache.set(name, value);

    return value;
}

const FlowStructure = load('document-builder:editor/flow/flow-structure');
const flow = new FlowStructure();
const layout = {
    capabilities: ['layout.flow'], header: [], footer: [],
    sections: [{
        id: 'section_one', type: 'flow-section', children: [{
            id: 'heading_one', type: 'heading', content: [{type: 'text', text: 'One', marks: []}],
            level: 2, keepWithNext: true,
        }], margin: {}, padding: {}, minHeight: {}, keepTogether: false, startNewPage: false,
    }],
};
assert.deepEqual(flow.insertionTarget(layout, 'section_one', 'heading'), {
    parentId: 'section_one', index: 1,
});
assert.deepEqual(flow.insertionTarget(layout, 'heading_one', 'paragraph'), {
    parentId: 'section_one', index: 1,
});
assert.deepEqual(flow.insertionTarget(layout, 'heading_one', 'flow-container'), {
    parentId: 'section_one', index: 1,
});
assert.deepEqual(flow.insertionTarget(layout, 'section_one', 'flow-section'), {
    region: 'sections', parentId: null, index: 1,
});

const shell = fs.readFileSync(path.join(sourceRoot, 'views/editor/shell.js'), 'utf8');
const template = fs.readFileSync(path.join(root,
    'files/client/custom/modules/document-builder/res/templates/editor/shell.tpl'), 'utf8');
const css = fs.readFileSync(path.join(root,
    'files/client/custom/modules/document-builder/res/css/editor.css'), 'utf8');
assert.match(shell, /addFlowNodeAtSelection/);
assert.match(shell, /application\/x-document-builder-flow/);
assert.doesNotMatch(template, /data-variable-search/);
assert.match(css, /drop\.is-compatible\.is-inside[\s\S]*min-height:\s*36px/);

console.log('Editor correction 01 insertion and drag-target tests passed.');
