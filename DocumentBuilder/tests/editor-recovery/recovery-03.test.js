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

const AddFlowNodeCommand = load('document-builder:editor/commands/add-flow-node');
const DuplicateNodeCommand = load('document-builder:editor/commands/duplicate-node');
const EditorState = load('document-builder:editor/state/editor-state');
const FlowStructure = load('document-builder:editor/flow/flow-structure');
const StableIdFactory = load('document-builder:editor/state/stable-id-factory');
const layout = JSON.parse(fs.readFileSync(path.join(root, 'tests/fixtures/layout/phase-08-default.json')));
const ids = ['section', 'container', 'heading', 'container_copy', 'heading_copy'];
const state = new EditorState(layout, new StableIdFactory(() => ids.shift()));
const flow = new FlowStructure({maxElements: 10});
const section = new AddFlowNodeCommand(flow, 'flow-section', {region: 'sections', parentId: null});
state.execute(section);
const container = new AddFlowNodeCommand(flow, 'flow-container', {parentId: section.addedId});
state.execute(container);
state.execute(new AddFlowNodeCommand(flow, 'heading', {parentId: container.addedId}));
const duplicate = new DuplicateNodeCommand(container.addedId, null, flow);
assert.equal(state.execute(duplicate), true);
assert.deepEqual(state.getLayout().sections[0].children.map(node => node.id), [
    container.addedId, duplicate.duplicateId,
]);
assert.notEqual(
    state.getLayout().sections[0].children[0].children[0].id,
    state.getLayout().sections[0].children[1].children[0].id,
);
assert.equal(state.undo(), true);
assert.equal(state.getLayout().sections[0].children.length, 1);

const shell = fs.readFileSync(path.join(sourceRoot, 'views/editor/shell.js'), 'utf8');
const canvas = fs.readFileSync(path.join(sourceRoot, 'editor/canvas/document-canvas.js'), 'utf8');
const css = fs.readFileSync(path.join(root,
    'files/client/custom/modules/document-builder/res/css/editor.css'), 'utf8');
for (const action of ['editFlowNode', 'duplicateFlowNode', 'removeFlowNode']) {
    assert.match(canvas, new RegExp(action));
}
assert.match(shell, /new DuplicateNodeCommand\(nodeId, null, this\.flowStructure\)/);
assert.match(shell, /event\.key === 'Escape'[\s\S]*cancelFlowDrag\(\)/);
assert.match(shell, /updateDropTargetCompatibility/);
assert.match(css, /is-dragging[^}]*drop\.is-compatible/s);
assert.match(css, /hover-toolbar[\s\S]*position:\s*absolute/);

console.log('Editor recovery 03 hover command and drag-state tests passed.');
