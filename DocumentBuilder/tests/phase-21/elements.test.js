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
const EditorState = load('document-builder:editor/state/editor-state');
const StableIdFactory = load('document-builder:editor/state/stable-id-factory');
const FlowStructure = load('document-builder:editor/flow/flow-structure');
const AddFlowNodeCommand = load('document-builder:editor/commands/add-flow-node');
const MoveFlowNodeCommand = load('document-builder:editor/commands/move-flow-node');
const UpdateNodeCommand = load('document-builder:editor/commands/update-node');
const LayoutPrecheck = load('document-builder:editor/validation/layout-precheck');
const layout = JSON.parse(fs.readFileSync(path.join(root, 'tests/fixtures/layout/phase-08-default.json')));
const ids = ['section', 'container', 'divider', 'spacer', 'pageBreak'];
const state = new EditorState(layout, new StableIdFactory(() => ids.shift()));
const flow = new FlowStructure();
const add = (type, parentId) => {
    const command = new AddFlowNodeCommand(flow, type, parentId ? {parentId} : {region: 'sections', parentId: null});
    assert.equal(state.execute(command), true); return command.addedId;
};
const sectionId = add('flow-section'); const containerId = add('flow-container', sectionId);
const dividerId = add('divider', containerId); const spacerId = add('spacer', containerId); const pageBreakId = add('page-break', containerId);
let children = state.getLayout().sections[0].children[0].children;
assert.deepEqual(children.map(node => node.type), ['divider', 'spacer', 'page-break']);
assert.deepEqual(children[0], {id: dividerId, type: 'divider', orientation: 'horizontal', style: 'solid', color: '#666666', thickness: {value: 0.5, unit: 'mm'}, length: {value: 100, unit: 'mm'}});
assert.equal(state.execute(new UpdateNodeCommand(dividerId, {orientation: 'vertical', style: 'dashed', thickness: {value: 2, unit: 'mm'}})), true);
assert.equal(state.execute(new UpdateNodeCommand(spacerId, {height: {value: 25, unit: 'mm'}})), true);
assert.equal(state.execute(new MoveFlowNodeCommand(flow, pageBreakId, {parentId: containerId, index: 0})), true);
children = state.getLayout().sections[0].children[0].children;
assert.deepEqual(children.map(node => node.type), ['page-break', 'divider', 'spacer']);
assert.equal(state.undo(), true); assert.equal(state.redo(), true);
const serialized = JSON.stringify(state.getLayout());
assert.deepEqual(JSON.parse(serialized).sections[0].children[0].children.map(node => node.type), ['page-break', 'divider', 'spacer']);
assert.equal(new LayoutPrecheck([], {}).check(state.getLayout()).valid, true);
const invalid = JSON.parse(serialized); invalid.sections[0].children[0].children[1].thickness.value = 20.1;
assert.equal(new LayoutPrecheck([], {}).check(invalid).valid, false);
assert.throws(() => flow.assertTarget(state.getLayout(), flow.createNode('spacer'), {parentId: dividerId}), /cannot contain/);
console.log('Phase 21 divider, spacer, page-break, reorder, serialization, history, and bounds tests passed.');
